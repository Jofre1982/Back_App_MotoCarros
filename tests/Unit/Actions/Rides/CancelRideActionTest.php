<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\CancelRideAction;
use App\Enums\RideStatus;
use App\Models\Ride;
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

        $this->assertSame(RideStatus::Cancelled, $resultado->status);
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
}
