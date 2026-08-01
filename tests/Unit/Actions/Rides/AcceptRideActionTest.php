<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\AcceptRideAction;
use App\Enums\RideStatus;
use App\Events\Realtime\RideNoLongerAvailable;
use App\Exceptions\Rides\RideNoLongerAvailableException;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver
 * .claude/STANDARDS.md).
 */
class AcceptRideActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_asigna_el_conductor_y_pasa_el_viaje_a_accepted(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $resultado = $this->app->make(AcceptRideAction::class)->handle($viaje, $conductor);

        $this->assertSame(RideStatus::Accepted, $resultado->status);
        $this->assertSame($conductor->getKey(), $resultado->driver_id);
        $this->assertSame(RideStatus::Accepted, $viaje->refresh()->status);
        $this->assertSame($conductor->getKey(), $viaje->driver_id);
    }

    public function test_rechaza_un_viaje_que_ya_no_esta_requested(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted]);

        $this->expectException(RideNoLongerAvailableException::class);

        try {
            $this->app->make(AcceptRideAction::class)->handle($viaje, $conductor);
        } finally {
            $this->assertNull($viaje->refresh()->driver_id);
        }
    }

    /**
     * Historia #17: avisa a los demás conductores cercanos que el viaje ya
     * no está disponible.
     */
    public function test_dispara_ride_no_longer_available_para_los_demas_conductores_cercanos(): void
    {
        Event::fake([RideNoLongerAvailable::class]);
        $viaje = Ride::factory()->create([
            'status' => RideStatus::Requested,
            'origin_latitude' => 4.710989,
            'origin_longitude' => -74.072092,
        ]);
        $aceptante = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $aceptante->id]);
        $otroCercano = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $otroCercano->id]);

        $this->app->make(AcceptRideAction::class)->handle($viaje, $aceptante);

        Event::assertDispatched(
            RideNoLongerAvailable::class,
            fn (RideNoLongerAvailable $evento): bool => $evento->rideId === $viaje->id
                && $evento->driverIds === [$otroCercano->id],
        );
    }

    public function test_no_dispara_ride_no_longer_available_para_el_conductor_que_acepto(): void
    {
        Event::fake([RideNoLongerAvailable::class]);
        $viaje = Ride::factory()->create([
            'status' => RideStatus::Requested,
            'origin_latitude' => 4.710989,
            'origin_longitude' => -74.072092,
        ]);
        $aceptante = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $aceptante->id]);

        $this->app->make(AcceptRideAction::class)->handle($viaje, $aceptante);

        Event::assertNotDispatched(RideNoLongerAvailable::class);
    }
}
