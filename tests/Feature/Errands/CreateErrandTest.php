<?php

declare(strict_types=1);

namespace Tests\Feature\Errands;

use App\Models\Errand;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/errands — ver openapi.yaml.
 *
 * Historia #92: el pasajero pide un mandado con descripción libre, un punto
 * de recogida libre y un sitio de entrega del catálogo. Sin tarifa fija: el
 * precio nace en `null` y lo fija el conductor al aceptar.
 */
class CreateErrandTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/errands';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function cuerpoValido(int $siteId): array
    {
        return [
            'description' => 'Recoger un paquete en la farmacia del centro.',
            'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
            'destination_site_id' => $siteId,
        ];
    }

    public function test_el_pasajero_pide_un_mandado_sin_foto(): void
    {
        $pasajero = User::factory()->create();
        $sitio = Site::factory()->create(['name' => 'Casco urbano']);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, $this->cuerpoValido($sitio->id))
            ->assertCreated();

        $respuesta->assertJsonPath('data.status', 'requested');
        $respuesta->assertJsonPath('data.has_photo', false);
        $respuesta->assertJsonPath('data.photo_url', null);
        $respuesta->assertJsonPath('data.destination.site_id', $sitio->id);
        $respuesta->assertJsonPath('data.agreed_price', null);
        $respuesta->assertJsonPath('data.driver', null);

        $this->assertDatabaseHas('errands', [
            'passenger_id' => $pasajero->id,
            'status' => 'requested',
            'destination_site_id' => $sitio->id,
        ]);
    }

    public function test_el_pasajero_adjunta_una_foto_y_queda_expuesta_en_la_respuesta(): void
    {
        $pasajero = User::factory()->create();
        $sitio = Site::factory()->create();
        $foto = UploadedFile::fake()->create('paquete.jpg', 100, 'image/jpeg');

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->post(self::URI, [...$this->cuerpoValido($sitio->id), 'photo' => $foto])
            ->assertCreated();

        $respuesta->assertJsonPath('data.has_photo', true);

        $mandado = Errand::query()->firstOrFail();
        $this->assertStringContainsString("/errands/{$mandado->id}/photo", (string) $respuesta->json('data.photo_url'));

        Storage::disk('local')->assertExists($mandado->photo_path);
    }

    public function test_rechaza_un_sitio_de_destino_inexistente(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, $this->cuerpoValido(999999))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination_site_id');

        $this->assertDatabaseCount('errands', 0);
    }

    public function test_rechaza_la_entrada_sin_descripcion(): void
    {
        $pasajero = User::factory()->create();
        $sitio = Site::factory()->create();
        $cuerpo = $this->cuerpoValido($sitio->id);
        unset($cuerpo['description']);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, $cuerpo)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_la_cuenta_de_conductor_no_puede_pedir_un_mandado(): void
    {
        $conductor = User::factory()->driver()->create();
        $sitio = Site::factory()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, $this->cuerpoValido($sitio->id))
            ->assertForbidden();

        $this->assertDatabaseCount('errands', 0);
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $sitio = Site::factory()->create();

        $this->postJson(self::URI, $this->cuerpoValido($sitio->id))
            ->assertUnauthorized();
    }
}
