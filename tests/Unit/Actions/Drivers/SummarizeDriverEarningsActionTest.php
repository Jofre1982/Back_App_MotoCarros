<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Drivers;

use App\Actions\Drivers\SummarizeDriverEarningsAction;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 *
 * Historia #30: cuánto ganó un conductor en un rango de fechas y cuántos
 * viajes completados componen ese total.
 */
class SummarizeDriverEarningsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suma_el_monto_ganado_de_los_viajes_completados_en_el_rango(): void
    {
        $conductor = User::factory()->driver()->create();

        Ride::factory()->create([
            'driver_id' => $conductor->id,
            'status' => RideStatus::Completed,
            'final_fare' => 9000,
            'completed_at' => Carbon::parse('2026-07-10 12:00:00'),
        ]);
        Ride::factory()->create([
            'driver_id' => $conductor->id,
            'status' => RideStatus::Completed,
            'final_fare' => 8500,
            'completed_at' => Carbon::parse('2026-07-20 12:00:00'),
        ]);

        $resumen = $this->action()->handle(
            $conductor,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertSame(17500, $resumen->totalEarned);
        $this->assertSame(2, $resumen->completedRides);
    }

    public function test_no_cuenta_viajes_completados_fuera_del_rango(): void
    {
        $conductor = User::factory()->driver()->create();

        Ride::factory()->create([
            'driver_id' => $conductor->id,
            'status' => RideStatus::Completed,
            'final_fare' => 9000,
            'completed_at' => Carbon::parse('2026-06-30 23:59:59'),
        ]);

        $resumen = $this->action()->handle(
            $conductor,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertSame(0, $resumen->totalEarned);
        $this->assertSame(0, $resumen->completedRides);
    }

    /**
     * El rango es inclusive en ambos extremos: un viaje completado en
     * cualquier momento del último día del rango sí cuenta.
     */
    public function test_incluye_el_dia_completo_de_los_extremos_del_rango(): void
    {
        $conductor = User::factory()->driver()->create();

        Ride::factory()->create([
            'driver_id' => $conductor->id,
            'status' => RideStatus::Completed,
            'final_fare' => 5000,
            'completed_at' => Carbon::parse('2026-07-31 23:59:00'),
        ]);

        $resumen = $this->action()->handle(
            $conductor,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertSame(5000, $resumen->totalEarned);
        $this->assertSame(1, $resumen->completedRides);
    }

    public function test_no_cuenta_viajes_cancelados_ni_activos_en_el_rango(): void
    {
        $conductor = User::factory()->driver()->create();

        Ride::factory()->create([
            'driver_id' => $conductor->id,
            'status' => RideStatus::Cancelled,
            'final_fare' => null,
        ]);
        Ride::factory()->create([
            'driver_id' => $conductor->id,
            'status' => RideStatus::InProgress,
            'final_fare' => null,
        ]);

        $resumen = $this->action()->handle(
            $conductor,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31'),
        );

        $this->assertSame(0, $resumen->totalEarned);
        $this->assertSame(0, $resumen->completedRides);
    }

    public function test_no_cuenta_viajes_completados_de_otro_conductor(): void
    {
        $conductor = User::factory()->driver()->create();

        Ride::factory()->create([
            'driver_id' => User::factory()->driver(),
            'status' => RideStatus::Completed,
            'final_fare' => 9000,
            'completed_at' => Carbon::parse('2026-07-10 12:00:00'),
        ]);

        $resumen = $this->action()->handle(
            $conductor,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertSame(0, $resumen->totalEarned);
        $this->assertSame(0, $resumen->completedRides);
    }

    private function action(): SummarizeDriverEarningsAction
    {
        return $this->app->make(SummarizeDriverEarningsAction::class);
    }
}
