<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ErrandStatus;
use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * `counterpart` en viajes y mandados — ver openapi.yaml: con el precio ya
 * confirmado, cada uno ve el nombre y el celular del otro para poder
 * comunicarse; antes, `null`.
 */
class CounterpartContactTest extends TestCase
{
    use RefreshDatabase;

    private function cambiarDeCuenta(): void
    {
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();
    }

    public function test_con_el_precio_confirmado_cada_uno_ve_al_otro_en_un_viaje(): void
    {
        $pasajero = User::factory()->create(['name' => 'Ana', 'phone' => '+573001111111']);
        $conductor = User::factory()->driver()->create(['name' => 'Luis', 'phone' => '+573002222222']);
        DriverProfile::factory()->create(['user_id' => $conductor->id]);
        $viaje = Ride::factory()->pickupOnly()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => RideStatus::Accepted,
            'agreed_price' => 7000,
            'price_confirmed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertJsonPath('data.counterpart', ['name' => 'Luis', 'phone' => '+573002222222']);

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertJsonPath('data.counterpart', ['name' => 'Ana', 'phone' => '+573001111111']);
    }

    public function test_sin_precio_confirmado_no_se_comparte_el_celular(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->pickupOnly()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => User::factory()->driver()->create()->id,
            'status' => RideStatus::Accepted,
            'agreed_price' => 7000,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertJsonPath('data.counterpart', null);
    }

    public function test_en_un_mandado_confirmado_el_pasajero_ve_al_conductor(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create(['name' => 'Luis', 'phone' => '+573002222222']);
        Errand::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => ErrandStatus::Accepted,
            'agreed_price' => 9000,
            'price_confirmed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson('/api/v1/me/errands')
            ->assertJsonPath('data.0.counterpart', ['name' => 'Luis', 'phone' => '+573002222222']);
    }

    public function test_un_mandado_terminado_ya_no_comparte_el_celular(): void
    {
        $pasajero = User::factory()->create();
        Errand::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => User::factory()->driver()->create()->id,
            'status' => ErrandStatus::Completed,
            'agreed_price' => 9000,
            'price_confirmed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson('/api/v1/me/errands')
            ->assertJsonPath('data.0.counterpart', null);
    }
}
