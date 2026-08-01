<?php

declare(strict_types=1);

namespace Tests\Feature\Drivers;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/me/earnings — ver openapi.yaml.
 *
 * Historia #30: el conductor hace seguimiento de sus ingresos por rango de
 * fechas. Es el complemento de `GET /me/rides`
 * (`tests/Feature/Rides/ShowRideHistoryTest.php`), no un reemplazo.
 */
class ShowDriverEarningsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/earnings';

    public function test_el_conductor_ve_el_total_ganado_y_los_viajes_completados_del_rango(): void
    {
        $conductor = User::factory()->driver()->create();

        Ride::factory()->create([
            'driver_id' => $conductor->id,
            'status' => RideStatus::Completed,
            'final_fare' => 9000,
            'completed_at' => Carbon::parse('2026-07-10 12:00:00'),
        ]);
        Ride::factory()->create([
            'driver_id' => $conductor->id,
            'status' => RideStatus::Completed,
            'final_fare' => 8500,
            'completed_at' => Carbon::parse('2026-07-20 12:00:00'),
        ]);
        // Fuera del rango solicitado: no debería sumar.
        Ride::factory()->create([
            'driver_id' => $conductor->id,
            'status' => RideStatus::Completed,
            'final_fare' => 5000,
            'completed_at' => Carbon::parse('2026-06-15 12:00:00'),
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI.'?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.from', '2026-07-01')
            ->assertJsonPath('data.to', '2026-07-31')
            ->assertJsonPath('data.currency', 'COP')
            ->assertJsonPath('data.total_earned', 17500)
            ->assertJsonPath('data.completed_rides', 2);
    }

    public function test_un_conductor_sin_viajes_completados_en_el_rango_recibe_cero(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI.'?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.total_earned', 0)
            ->assertJsonPath('data.completed_rides', 0);
    }

    public function test_no_incluye_ganancias_de_otro_conductor(): void
    {
        $conductor = User::factory()->driver()->create();
        Ride::factory()->create([
            'driver_id' => User::factory()->driver(),
            'status' => RideStatus::Completed,
            'final_fare' => 9000,
            'completed_at' => Carbon::parse('2026-07-10 12:00:00'),
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI.'?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.total_earned', 0)
            ->assertJsonPath('data.completed_rides', 0);
    }

    public function test_rechaza_un_rango_con_from_posterior_a_to(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI.'?from=2026-07-31&to=2026-07-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_rechaza_from_ausente(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI.'?to=2026-07-31')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    public function test_rechaza_to_ausente(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI.'?from=2026-07-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_rechaza_una_fecha_que_no_es_valida(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI.'?from=no-es-una-fecha&to=2026-07-31')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    public function test_rechaza_a_un_pasajero(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson(self::URI.'?from=2026-07-01&to=2026-07-31')
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $this->getJson(self::URI.'?from=2026-07-01&to=2026-07-31')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }
}
