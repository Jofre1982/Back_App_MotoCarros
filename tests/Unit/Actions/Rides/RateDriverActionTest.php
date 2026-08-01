<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\RateDriverAction;
use App\Enums\RatedRole;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class RateDriverActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_la_calificacion_asociada_al_viaje(): void
    {
        $viaje = $this->viajeCompletado();

        $rating = $this->app->make(RateDriverAction::class)->handle($viaje, 5, 'Excelente servicio.');

        $this->assertSame($viaje->id, $rating->ride_id);
        $this->assertSame(RatedRole::Driver, $rating->rated_role);
        $this->assertSame(5, $rating->score);
        $this->assertSame('Excelente servicio.', $rating->comment);
    }

    public function test_el_comentario_puede_quedar_en_null(): void
    {
        $viaje = $this->viajeCompletado();

        $rating = $this->app->make(RateDriverAction::class)->handle($viaje, 3, null);

        $this->assertNull($rating->comment);
    }

    public function test_persiste_la_fila_en_la_base(): void
    {
        $viaje = $this->viajeCompletado();

        $rating = $this->app->make(RateDriverAction::class)->handle($viaje, 4, null);

        $this->assertDatabaseHas('ride_ratings', [
            'id' => $rating->id,
            'ride_id' => $viaje->id,
            'rated_role' => RatedRole::Driver->value,
            'score' => 4,
        ]);
    }

    private function viajeCompletado(): Ride
    {
        return Ride::factory()->create([
            'status' => RideStatus::Completed,
            'driver_id' => User::factory()->driver(),
            'completed_at' => now(),
        ]);
    }
}
