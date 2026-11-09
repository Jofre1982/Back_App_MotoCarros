<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Actions\Errands\AcceptErrandAction;
use App\Actions\Errands\RespondToErrandPriceAction;
use App\Actions\Rides\AcceptRideAction;
use App\Actions\Rides\RespondToRidePriceAction;
use App\Enums\ErrandStatus;
use App\Enums\RideStatus;
use App\Enums\VehicleType;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * `driver_vehicle` y `passenger_name` en viajes y mandados — ver openapi.yaml.
 *
 * Pedido del dueño: en el historial tiene que verse quién hizo el servicio
 * y con qué vehículo, "en caso de alguna eventualidad". La placa se guarda
 * al aceptar: si el conductor cambia de vehículo, el historial no cambia.
 */
class ServiceHistoryDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
    }

    public function test_el_viaje_guarda_la_placa_con_la_que_se_acepto(): void
    {
        $pasajero = User::factory()->create(['name' => 'María Pérez']);
        $conductor = $this->conductorCon('ABC12D');
        $viaje = Ride::factory()->pickupOnly()->create(['passenger_id' => $pasajero->id]);

        app(AcceptRideAction::class)->handle($viaje, $conductor, 7000);
        // Después cambia de vehículo: el viaje sigue diciendo ABC12D.
        $conductor->vehicle()->update(['plate' => 'ZZZ99Z', 'type' => VehicleType::Motocarga]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertOk()
            ->assertJsonPath('data.driver_vehicle', ['plate' => 'ABC12D', 'type' => 'motocarro'])
            ->assertJsonPath('data.passenger_name', null);
    }

    public function test_el_conductor_ve_el_nombre_del_pasajero_en_su_historial(): void
    {
        $pasajero = User::factory()->create(['name' => 'María Pérez']);
        $conductor = $this->conductorCon('ABC12D');
        Ride::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => RideStatus::Completed,
            'driver_vehicle_plate' => 'ABC12D',
            'driver_vehicle_type' => VehicleType::Motocarro,
        ]);
        Errand::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => ErrandStatus::Completed,
            'driver_vehicle_plate' => 'ABC12D',
            'driver_vehicle_type' => VehicleType::Motocarro,
        ]);
        $token = JWTAuth::fromUser($conductor);

        $this->withToken($token)
            ->getJson('/api/v1/me/rides?role=driver')
            ->assertOk()
            ->assertJsonPath('data.0.passenger_name', 'María Pérez')
            ->assertJsonPath('data.0.driver_vehicle.plate', 'ABC12D');

        $this->withToken($token)
            ->getJson('/api/v1/me/errands?as=driver')
            ->assertOk()
            ->assertJsonPath('data.0.passenger_name', 'María Pérez')
            ->assertJsonPath('data.0.driver_vehicle.plate', 'ABC12D');
    }

    public function test_el_mandado_guarda_la_placa_al_aceptar_y_la_borra_si_rechazan_el_precio(): void
    {
        $conductor = $this->conductorCon('MOT45E', VehicleType::Motocarga);
        $mandado = Errand::factory()->create(['vehicle_type' => VehicleType::Motocarga]);

        $mandado = app(AcceptErrandAction::class)->handle($mandado, $conductor, 20000);
        $this->assertSame('MOT45E', $mandado->refresh()->driver_vehicle_plate);
        $this->assertSame(VehicleType::Motocarga, $mandado->driver_vehicle_type);

        app(RespondToErrandPriceAction::class)->handle($mandado, false);
        $this->assertNull($mandado->refresh()->driver_vehicle_plate);
        $this->assertNull($mandado->driver_vehicle_type);
    }

    public function test_si_rechazan_el_precio_del_viaje_se_borra_la_placa(): void
    {
        $conductor = $this->conductorCon('ABC12D');
        $viaje = Ride::factory()->pickupOnly()->create();

        $viaje = app(AcceptRideAction::class)->handle($viaje, $conductor, 5000);
        app(RespondToRidePriceAction::class)->handle($viaje, false);

        $this->assertNull($viaje->refresh()->driver_vehicle_plate);
    }

    public function test_sin_conductor_no_hay_vehiculo(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->create(['passenger_id' => $pasajero->id]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertOk()
            ->assertJsonPath('data.driver_vehicle', null);
    }

    private function conductorCon(string $placa, VehicleType $tipo = VehicleType::Motocarro): User
    {
        $conductor = User::factory()->driver()->create();
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => $placa, 'type' => $tipo]);

        return $conductor;
    }
}
