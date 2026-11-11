<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Anuncios de negocios locales: el admin los carga en el panel y el
 * pasajero los ve mientras espera. Ver openapi.yaml (/admin/ads y /ads).
 */
class AdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Config::set('ads.max_active', 5);
        // 10:00 en Colombia del 12 de noviembre.
        $this->travelTo('2026-11-12 15:00:00');
    }

    public function test_el_admin_crea_un_anuncio_con_imagen(): void
    {
        $id = $this->comoAdmin()
            ->post('/api/v1/admin/ads', [
                'business_name' => 'Droguería La 14',
                'message' => 'Domicilios gratis en el pueblo',
                'whatsapp' => '3001234567',
                'starts_on' => '2026-11-12',
                'ends_on' => '2026-12-12',
                'image' => UploadedFile::fake()->image('logo.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.views', 0)
            ->json('data.id');

        $ad = Ad::query()->findOrFail($id);
        $this->assertNotNull($ad->image_path);
        Storage::disk('local')->assertExists($ad->image_path);
    }

    public function test_no_deja_pasar_de_los_cupos_el_mismo_dia(): void
    {
        Ad::factory()->count(5)->create(['starts_on' => '2026-11-12', 'ends_on' => '2026-11-30']);

        $this->comoAdmin()
            ->postJson('/api/v1/admin/ads', $this->datos('2026-11-20', '2026-11-25'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slots');
    }

    public function test_cuenta_el_dia_mas_lleno_y_no_el_total_del_rango(): void
    {
        Ad::factory()->count(3)->create(['starts_on' => '2026-11-12', 'ends_on' => '2026-12-31']);
        Ad::factory()->create(['starts_on' => '2026-11-12', 'ends_on' => '2026-11-18']);
        Ad::factory()->create(['starts_on' => '2026-12-20', 'ends_on' => '2026-12-31']);
        Ad::factory()->create(['starts_on' => '2026-11-12', 'ends_on' => '2026-12-31', 'is_paused' => true]);

        $this->comoAdmin()
            ->postJson('/api/v1/admin/ads', $this->datos('2026-11-12', '2026-12-31'))
            ->assertCreated();
    }

    public function test_pausar_reanudar_y_editar(): void
    {
        $ad = Ad::factory()->create();

        $this->comoAdmin()
            ->patchJson("/api/v1/admin/ads/{$ad->id}", ['is_paused' => true, 'message' => 'Nuevo texto'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paused')
            ->assertJsonPath('data.message', 'Nuevo texto');

        Ad::factory()->count(5)->create();
        $this->comoAdmin()
            ->patchJson("/api/v1/admin/ads/{$ad->id}", ['is_paused' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slots');

        $this->comoAdmin()
            ->patchJson("/api/v1/admin/ads/{$ad->id}", ['ends_on' => '2026-11-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');
    }

    public function test_cambiar_la_imagen_y_borrar(): void
    {
        $ad = Ad::factory()->create(['image_path' => UploadedFile::fake()->image('a.png')->store('ads', 'local')]);
        $vieja = $ad->image_path;

        $this->comoAdmin()
            ->post("/api/v1/admin/ads/{$ad->id}/image", ['image' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertNotNull($vieja);
        Storage::disk('local')->assertMissing($vieja);

        $nueva = $ad->refresh()->image_path;
        $this->comoAdmin()->deleteJson("/api/v1/admin/ads/{$ad->id}")->assertNoContent();
        $this->assertNotNull($nueva);
        Storage::disk('local')->assertMissing($nueva);
        $this->assertDatabaseMissing('ads', ['id' => $ad->id]);
    }

    public function test_un_pasajero_no_administra_anuncios(): void
    {
        $this->comoPasajero()->getJson('/api/v1/admin/ads')->assertForbidden();
        $this->comoPasajero()->postJson('/api/v1/admin/ads', $this->datos('2026-11-12', '2026-11-20'))->assertForbidden();
    }

    public function test_el_pasajero_ve_solo_los_que_salen_hoy(): void
    {
        $hoy = Ad::factory()->create(['business_name' => 'Hoy']);
        Ad::factory()->create(['starts_on' => '2026-11-13', 'ends_on' => '2026-11-20']);
        Ad::factory()->create(['starts_on' => '2026-11-01', 'ends_on' => '2026-11-11']);
        Ad::factory()->create(['is_paused' => true]);

        $this->comoPasajero()->getJson('/api/v1/ads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hoy->id)
            ->assertJsonMissingPath('data.0.views');
    }

    public function test_el_ultimo_dia_en_colombia_todavia_sale(): void
    {
        // 23:30 del 12 en Colombia = 04:30 del 13 en UTC.
        $this->travelTo('2026-11-13 04:30:00');
        Ad::factory()->create(['starts_on' => '2026-11-01', 'ends_on' => '2026-11-12']);

        $this->comoPasajero()->getJson('/api/v1/ads')->assertJsonCount(1, 'data');
    }

    public function test_cuenta_vistas_y_toques_y_sirve_la_imagen(): void
    {
        $ad = Ad::factory()->create(['image_path' => UploadedFile::fake()->image('a.png')->store('ads', 'local')]);

        $this->comoPasajero()->postJson("/api/v1/ads/{$ad->id}/view")->assertNoContent();
        $this->comoPasajero()->postJson("/api/v1/ads/{$ad->id}/view")->assertNoContent();
        $this->comoPasajero()->postJson("/api/v1/ads/{$ad->id}/click")->assertNoContent();
        $this->comoPasajero()->get("/api/v1/ads/{$ad->id}/image")->assertOk();

        $this->assertSame(2, $ad->refresh()->views);
        $this->assertSame(1, $ad->clicks);
    }

    public function test_sin_token_no_hay_anuncios(): void
    {
        $this->getJson('/api/v1/ads')->assertUnauthorized();
    }

    /**
     * @return array<string, string>
     */
    private function datos(string $desde, string $hasta): array
    {
        return [
            'business_name' => 'Restaurante El Guainía',
            'message' => 'Almuerzo ejecutivo a $12.000',
            'starts_on' => $desde,
            'ends_on' => $hasta,
        ];
    }

    private function comoAdmin(): static
    {
        return $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()));
    }

    private function comoPasajero(): static
    {
        return $this->withToken(JWTAuth::fromUser(User::factory()->create()));
    }
}
