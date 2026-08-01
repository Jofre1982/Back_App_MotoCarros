<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\CancelRideAction;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver
 * .claude/STANDARDS.md).
 */
class CancelRideActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pasa_el_viaje_a_cancelled(): void
    {
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $resultado = $this->app->make(CancelRideAction::class)->handle($viaje);

        $this->assertSame(RideStatus::Cancelled, $resultado->ride->status);
        $this->assertSame(RideStatus::Cancelled, $viaje->refresh()->status);
    }

    public function test_libera_el_slot_de_viaje_activo_del_pasajero(): void
    {
        // `active_passenger_id` es una columna generada por la base (ver la
        // migración de `rides`): confirmar que se libera es lo que garantiza
        // que el pasajero pueda volver a solicitar un viaje después.
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->app->make(CancelRideAction::class)->handle($viaje);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'active_passenger_id' => null,
        ]);
    }

    public function test_cancelar_desde_requested_no_aplica_penalizacion(): void
    {
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $resultado = $this->app->make(CancelRideAction::class)->handle($viaje);

        $this->assertFalse($resultado->feeApplies);
    }

    public function test_cancelar_desde_accepted_aplica_penalizacion(): void
    {
        $conductor = User::factory()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $resultado = $this->app->make(CancelRideAction::class)->handle($viaje);

        $this->assertTrue($resultado->feeApplies);
    }

    public function test_cancelar_desde_accepted_libera_el_slot_de_viaje_activo_del_conductor(): void
    {
        // `active_driver_id` es una columna generada por la base (ver la
        // migración de `rides`): confirmar que se libera es lo que garantiza
        // que el conductor pueda aceptar otro viaje después.
        $conductor = User::factory()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $this->app->make(CancelRideAction::class)->handle($viaje);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'active_driver_id' => null,
        ]);
    }

    public function test_cancelar_desde_accepted_conserva_el_conductor_como_registro_historico(): void
    {
        $conductor = User::factory()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $this->app->make(CancelRideAction::class)->handle($viaje);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'driver_id' => $conductor->id,
        ]);
    }
}
