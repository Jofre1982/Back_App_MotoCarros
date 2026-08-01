<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Realtime;

use App\Actions\Realtime\ShareDriverLocationAction;
use App\DTOs\Coordinates;
use App\Enums\RideStatus;
use App\Events\Realtime\DriverLocationUpdated;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class ShareDriverLocationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispara_el_evento_con_el_viaje_el_conductor_y_las_coordenadas(): void
    {
        Event::fake([DriverLocationUpdated::class]);

        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
        ]);
        $ubicacion = new Coordinates(latitude: 4.706, longitude: -74.068);

        $this->app->make(ShareDriverLocationAction::class)->handle($viaje, $ubicacion);

        Event::assertDispatched(DriverLocationUpdated::class, function (DriverLocationUpdated $evento) use ($viaje, $conductor): bool {
            return $evento->rideId === $viaje->id
                && $evento->driverId === $conductor->id
                && $evento->latitude === 4.706
                && $evento->longitude === -74.068;
        });
    }
}
