<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Site;
use App\Models\SiteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/admin/sites — ver openapi.yaml.
 */
class ListSitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_los_sitios_con_sus_precios(): void
    {
        $sitio = Site::factory()->create(['name' => 'Casco urbano']);
        SiteFare::factory()->create([
            'site_id' => $sitio->id,
            'vehicle_type' => 'motocarro',
            'pricing_unit' => 'per_person',
            'day_price' => 4000,
            'night_price' => 5000,
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->getJson('/api/v1/admin/sites')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Casco urbano')
            ->assertJsonPath('data.0.fares.0.vehicle_type', 'motocarro')
            ->assertJsonPath('data.0.fares.0.day_price', 4000)
            ->assertJsonPath('data.0.fares.0.night_price', 5000);
    }

    public function test_responde_lista_vacia_si_no_hay_sitios(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->getJson('/api/v1/admin/sites')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_un_pasajero_no_puede_listar_sitios(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson('/api/v1/admin/sites')
            ->assertForbidden();
    }

    public function test_rechaza_la_lista_sin_token(): void
    {
        $this->getJson('/api/v1/admin/sites')->assertUnauthorized();
    }
}
