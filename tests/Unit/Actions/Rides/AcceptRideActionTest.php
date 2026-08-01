<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\AcceptRideAction;
use App\Enums\RideStatus;
use App\Exceptions\Rides\RideNoLongerAvailableException;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
