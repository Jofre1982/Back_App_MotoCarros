<?php

declare(strict_types=1);

namespace Tests\Feature\Errands;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/errands/{id}/photo — ver openapi.yaml.
 *
 * Historia #92: la foto de un mandado solo la ven el pasajero dueño y el
 * conductor asignado, mismo criterio que `ErrandPolicy::view()`.
 */
class ShowErrandPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function uri(Errand $mandado): string
    {
        return "/api/v1/errands/{$mandado->id}/photo";
    }

    private function mandadoConFoto(array $atributos = []): Errand
    {
        $ruta = UploadedFile::fake()->create('paquete.jpg', 100, 'image/jpeg')->store('errand-photos', 'local');

        return Errand::factory()->create([...$atributos, 'photo_path' => $ruta]);
    }

    public function test_el_pasajero_dueno_ve_la_foto(): void
    {
        $pasajero = User::factory()->create();
        $mandado = $this->mandadoConFoto(['passenger_id' => $pasajero->id]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->get($this->uri($mandado))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_el_conductor_asignado_ve_la_foto(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = $this->mandadoConFoto([
            'status' => ErrandStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->get($this->uri($mandado))
            ->assertOk();
    }

    public function test_un_tercero_no_puede_ver_la_foto(): void
    {
        $tercero = User::factory()->create();
        $mandado = $this->mandadoConFoto();

        $this->withToken(JWTAuth::fromUser($tercero))
            ->get($this->uri($mandado))
            ->assertForbidden();
    }

    public function test_responde_404_cuando_el_mandado_no_tiene_foto(): void
    {
        $pasajero = User::factory()->create();
        $mandado = Errand::factory()->create(['passenger_id' => $pasajero->id, 'photo_path' => null]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->get($this->uri($mandado))
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $mandado = $this->mandadoConFoto();

        $this->get($this->uri($mandado))->assertUnauthorized();
    }
}
