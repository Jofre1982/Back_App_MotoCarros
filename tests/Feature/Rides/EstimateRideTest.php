<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\PricingUnit;
use App\Enums\VehicleType;
use App\Models\Site;
use App\Models\SiteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/rides/estimate — ver openapi.yaml.
 *
 * Lo que fija esta suite desde la historia #87: que la respuesta traiga el
 * sitio de destino y el monto de su precio fijo vigente (por persona o por
 * viaje, según el sitio), que un sitio sin precio de pasajero responda 422 en
 * vez de reventar, y que el endpoint exija un token vigente igual que el
 * resto de la API.
 */
class EstimateRideTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/rides/estimate';

    private Site $sitio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sitio = $this->siteConTarifa(8850);
    }

    public function test_devuelve_el_sitio_de_destino_y_la_tarifa_estimada(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'destination' => ['site_id' => $this->sitio->id, 'name' => $this->sitio->name],
                    'passenger_count' => 1,
                    'currency' => 'COP',
                    'estimated_fare' => 8850,
                    'is_estimate' => true,
                ],
            ]);
    }

    public function test_multiplica_el_precio_por_pasajero_cuando_el_sitio_cobra_por_persona(): void
    {
        $sitio = $this->siteConTarifa(4000, PricingUnit::PerPerson);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'destination_site_id' => $sitio->id,
                'passenger_count' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.estimated_fare', 12000);
    }

    public function test_indica_explicitamente_que_el_monto_es_un_estimado(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertOk()
            ->assertJsonPath('data.is_estimate', true);
    }

    public function test_rechaza_un_sitio_sin_precio_de_pasajero(): void
    {
        $sitioSinPrecio = Site::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'destination_site_id' => $sitioSinPrecio->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination_site_id');
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $this->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_entrada_vacia(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'origin.latitude',
                'origin.longitude',
                'destination_site_id',
                'passenger_count',
            ]);
    }

    public function test_rechaza_una_latitud_de_origen_fuera_de_rango(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'origin' => ['latitude' => 95.0, 'longitude' => -74.0],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('origin.latitude');
    }

    public function test_rechaza_un_sitio_inexistente(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'destination_site_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination_site_id');
    }

    public function test_rechaza_mas_de_tres_pasajeros(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'passenger_count' => 4,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('passenger_count');
    }

    private function siteConTarifa(
        int $dayPrice,
        PricingUnit $pricingUnit = PricingUnit::PerTrip,
    ): Site {
        $site = Site::factory()->create();
        SiteFare::factory()->create([
            'site_id' => $site->id,
            'vehicle_type' => VehicleType::Motocarro,
            'pricing_unit' => $pricingUnit,
            'day_price' => $dayPrice,
        ]);

        return $site;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(): array
    {
        return [
            'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
            'destination_site_id' => $this->sitio->id,
            'passenger_count' => 1,
        ];
    }
}
