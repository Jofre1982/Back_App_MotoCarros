<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/rides/available — ver openapi.yaml.
 *
 * Un conductor que se conecta después de que el pasajero pidió el viaje no
 * recibió `ride.requested`; esta lista es la que le permite verlo igual.
 */
class ListAvailableRidesTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/rides/available';

    public function test_el_conductor_ve_los_viajes_esperando_del_mas_antiguo_al_mas_nuevo(): void
    {
        $nuevo = Ride::factory()->create(['status' => RideStatus::Requested, 'created_at' => Carbon::parse('2026-10-28 10:05')]);
        $viejo = Ride::factory()->create(['status' => RideStatus::Requested, 'created_at' => Carbon::parse('2026-10-28 10:00')]);
        Ride::factory()->create(['status' => RideStatus::Accepted]);
        Ride::factory()->create(['status' => RideStatus::Cancelled]);

        $this->withToken(JWTAuth::fromUser($this->conductor()))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $viejo->id)
            ->assertJsonPath('data.1.id', $nuevo->id)
            ->assertJsonPath('data.0.status', 'requested');
    }

    public function test_no_incluye_el_viaje_que_pidio_la_misma_cuenta(): void
    {
        $cuenta = $this->conductor();
        Ride::factory()->create(['status' => RideStatus::Requested, 'passenger_id' => $cuenta->id]);

        $this->withToken(JWTAuth::fromUser($cuenta))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_un_conductor_de_motocarga_recibe_la_lista_vacia(): void
    {
        $conductor = $this->conductor();
        Vehicle::factory()->motocarga()->create(['user_id' => $conductor->id]);
        Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_un_pasajero_no_puede_verlos(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(self::URI)
            ->assertForbidden();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    private function conductor(): User
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        return $conductor;
    }
}
