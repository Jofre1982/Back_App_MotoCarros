<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\StartRideAction;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class StartRideActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pasa_el_viaje_a_in_progress(): void
    {
        $viaje = $this->viajeAceptado();

        $resultado = $this->app->make(StartRideAction::class)->handle($viaje);

        $this->assertSame(RideStatus::InProgress, $resultado->status);
        $this->assertSame(RideStatus::InProgress, $viaje->refresh()->status);
    }

    public function test_registra_la_hora_de_inicio(): void
    {
        Carbon::setTestNow('2026-07-31 14:09:05');
        $viaje = $this->viajeAceptado();

        $this->app->make(StartRideAction::class)->handle($viaje);

        $this->assertNotNull($viaje->refresh()->started_at);
        $this->assertTrue(Carbon::now()->equalTo($viaje->started_at));
    }

    public function test_no_toca_al_conductor_asignado(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        $this->app->make(StartRideAction::class)->handle($viaje);

        $this->assertSame($conductor->getKey(), $viaje->refresh()->driver_id);
    }

    private function viajeAceptado(): Ride
    {
        return Ride::factory()->create([
            'status' => RideStatus::Accepted,
            'driver_id' => User::factory()->driver(),
        ]);
    }
}
