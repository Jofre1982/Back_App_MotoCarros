<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/rides/estimate — ver openapi.yaml.
 *
 * Lo que fija esta suite: que la respuesta traiga distancia, duración y monto
 * estimado calculados con el proveedor de mapas y la tarifa vigentes, que una
 * zona sin ruta disponible responda 422 en vez de reventar, y que el endpoint
 * exija un token vigente igual que el resto de la API.
 */
class EstimateRideTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/rides/estimate';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('maps.provider', 'google');
        Config::set('maps.google.key', 'test-key');

        Config::set('fares.currency', 'COP');
        Config::set('fares.base', 1500);
        Config::set('fares.per_kilometer', 800);
        Config::set('fares.per_minute', 100);
        Config::set('fares.per_waiting_minute', 60);
        Config::set('fares.minimum', 3000);
        Config::set('fares.rounding_step', 50);
    }

    public function test_devuelve_distancia_duracion_y_tarifa_estimada(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [['distanceMeters' => 7421, 'duration' => '842s']],
            ]),
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'distance_meters' => 7421,
                    'duration_seconds' => 842,
                    'currency' => 'COP',
                    'estimated_fare' => 8850,
                    'is_estimate' => true,
                ],
            ]);
    }

    public function test_indica_explicitamente_que_el_monto_es_un_estimado(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [['distanceMeters' => 1000, 'duration' => '120s']],
            ]),
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertOk()
            ->assertJsonPath('data.is_estimate', true);
    }

    public function test_responde_422_cuando_no_hay_ruta_entre_las_coordenadas(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response(['routes' => []]),
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        Http::fake();

        $this->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        Http::assertNothingSent();
    }

    public function test_rechaza_coordenadas_faltantes(): void
    {
        Http::fake();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'origin.latitude',
                'origin.longitude',
                'destination.latitude',
                'destination.longitude',
            ]);

        Http::assertNothingSent();
    }

    public function test_rechaza_una_latitud_fuera_de_rango(): void
    {
        Http::fake();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'origin' => ['latitude' => 95.0, 'longitude' => -74.0],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('origin.latitude');

        Http::assertNothingSent();
    }

    public function test_rechaza_una_longitud_fuera_de_rango(): void
    {
        Http::fake();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'destination' => ['latitude' => 4.7, 'longitude' => 200.0],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination.longitude');

        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(): array
    {
        return [
            'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
            'destination' => ['latitude' => 4.698, 'longitude' => -74.061],
        ];
    }
}
