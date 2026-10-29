<?php

declare(strict_types=1);

namespace Tests\Feature\Drivers;

use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * "Recibir viajes" apagado: el conductor puede usar la app sin que le lleguen
 * solicitudes (pedido del dueño). Prenderlo no exige ubicación.
 */
class RideAvailabilityListTest extends TestCase
{
    use RefreshDatabase;

    private function cambiarDeCuenta(): void
    {
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();
    }

    public function test_un_conductor_no_disponible_no_ve_viajes_esperando(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id, 'is_available' => false]);
        Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson('/api/v1/rides/available')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_al_activarse_sin_ubicacion_empieza_a_verlos(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id, 'is_available' => false]);
        Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson('/api/v1/me/availability', ['is_available' => true])
            ->assertOk()
            ->assertJsonPath('data.is_available', true);

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson('/api/v1/rides/available')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_con_ubicacion_la_guarda(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson('/api/v1/me/availability', ['is_available' => true, 'latitude' => 3.8653, 'longitude' => -67.9239])
            ->assertOk()
            ->assertJsonPath('data.latitude', 3.8653);
    }
}
