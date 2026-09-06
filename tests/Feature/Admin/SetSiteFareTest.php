<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de PUT /api/v1/admin/sites/{site}/fare — ver openapi.yaml.
 */
class SetSiteFareTest extends TestCase
{
    use RefreshDatabase;

    private function uri(Site $site): string
    {
        return "/api/v1/admin/sites/{$site->id}/fare";
    }

    public function test_fija_el_precio_de_un_sitio(): void
    {
        $sitio = Site::factory()->create(['name' => 'Casco urbano']);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->putJson($this->uri($sitio), [
                'vehicle_type' => 'motocarro',
                'pricing_unit' => 'per_person',
                'day_price' => 4000,
                'night_price' => 5000,
            ])
            ->assertOk()
            ->assertJsonPath('data.fares.0.day_price', 4000)
            ->assertJsonPath('data.fares.0.night_price', 5000);

        $this->assertDatabaseHas('site_fares', [
            'site_id' => $sitio->id,
            'vehicle_type' => 'motocarro',
            'day_price' => 4000,
            'night_price' => 5000,
        ]);
    }

    public function test_un_sitio_sin_recargo_nocturno_no_manda_night_price(): void
    {
        $sitio = Site::factory()->create(['name' => 'Aeropuerto']);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->putJson($this->uri($sitio), [
                'vehicle_type' => 'motocarro',
                'pricing_unit' => 'per_person',
                'day_price' => 6000,
            ])
            ->assertOk()
            ->assertJsonPath('data.fares.0.night_price', null);
    }

    public function test_reemplaza_el_precio_existente_del_mismo_tipo_de_vehiculo(): void
    {
        $sitio = Site::factory()->create();
        $this->withToken(JWTAuth::fromUser($admin = User::factory()->admin()->create()))
            ->putJson($this->uri($sitio), [
                'vehicle_type' => 'motocarro',
                'pricing_unit' => 'per_trip',
                'day_price' => 10000,
            ])->assertOk();

        $this->withToken(JWTAuth::fromUser($admin))
            ->putJson($this->uri($sitio), [
                'vehicle_type' => 'motocarro',
                'pricing_unit' => 'per_trip',
                'day_price' => 20000,
            ])->assertOk();

        $this->assertDatabaseCount('site_fares', 1);
        $this->assertDatabaseHas('site_fares', ['site_id' => $sitio->id, 'day_price' => 20000]);
    }

    public function test_un_sitio_puede_tener_precio_de_motocarro_y_de_motocarga_a_la_vez(): void
    {
        $sitio = Site::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->withToken(JWTAuth::fromUser($admin))
            ->putJson($this->uri($sitio), [
                'vehicle_type' => 'motocarro',
                'pricing_unit' => 'per_person',
                'day_price' => 4000,
            ])->assertOk();

        $this->withToken(JWTAuth::fromUser($admin))
            ->putJson($this->uri($sitio), [
                'vehicle_type' => 'motocarga',
                'pricing_unit' => 'per_trip',
                'day_price' => 20000,
            ])->assertOk();

        $this->assertDatabaseCount('site_fares', 2);
    }

    public function test_rechaza_un_precio_negativo(): void
    {
        $sitio = Site::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->putJson($this->uri($sitio), [
                'vehicle_type' => 'motocarro',
                'pricing_unit' => 'per_person',
                'day_price' => -100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('day_price');
    }

    public function test_responde_404_si_el_sitio_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->putJson('/api/v1/admin/sites/999999/fare', [
                'vehicle_type' => 'motocarro',
                'pricing_unit' => 'per_person',
                'day_price' => 4000,
            ])
            ->assertNotFound();
    }

    public function test_un_pasajero_no_puede_fijar_precios(): void
    {
        $sitio = Site::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->putJson($this->uri($sitio), [
                'vehicle_type' => 'motocarro',
                'pricing_unit' => 'per_person',
                'day_price' => 4000,
            ])
            ->assertForbidden();
    }
}
