<?php

declare(strict_types=1);

namespace Tests\Feature\Sites;

use App\Enums\PricingUnit;
use App\Enums\VehicleType;
use App\Models\Site;
use App\Models\SiteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/sites — ver openapi.yaml.
 *
 * A diferencia de GET /admin/sites (ver ListSitesTest de Admin), cualquier
 * cuenta autenticada puede consultar este catálogo: es de solo lectura, para
 * que el pasajero elija destino al pedir un viaje (historia #87).
 */
class ListSitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_los_sitios_con_sus_precios(): void
    {
        $sitio = Site::factory()->create(['name' => 'Casco urbano']);
        SiteFare::factory()->create([
            'site_id' => $sitio->id,
            'vehicle_type' => VehicleType::Motocarro,
            'pricing_unit' => PricingUnit::PerPerson,
            'day_price' => 4000,
            'night_price' => 5000,
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson('/api/v1/sites')
            ->assertOk()
            ->assertJsonPath('data.0.id', $sitio->id)
            ->assertJsonPath('data.0.name', 'Casco urbano')
            ->assertJsonPath('data.0.fares.0.day_price', 4000);
    }

    public function test_un_conductor_tambien_puede_consultar_el_catalogo(): void
    {
        Site::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->getJson('/api/v1/sites')
            ->assertOk();
    }

    public function test_rechaza_la_consulta_sin_token(): void
    {
        $this->getJson('/api/v1/sites')->assertUnauthorized();
    }
}
