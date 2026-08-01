<?php

declare(strict_types=1);

namespace App\Actions\Drivers;

use App\DTOs\DriverEarningsSummary;
use App\Enums\RideStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Calcula cuánto ganó un conductor en un rango de fechas y cuántos viajes
 * completados componen ese total (historia #30).
 *
 * Solo cuentan los viajes `completed` cuyo `completed_at` cae dentro del
 * rango (inclusive en ambos extremos): uno cancelado o todavía activo no le
 * generó ingreso al conductor. La moneda sale de `config('fares.currency')`
 * y no del viaje: es la misma para todos los viajes de la plataforma (ver
 * `FareSchedule`), y un conductor sin viajes en el rango igual necesita saber
 * en qué moneda leer un total en cero.
 */
final readonly class SummarizeDriverEarningsAction
{
    public function handle(User $driver, Carbon $from, Carbon $to): DriverEarningsSummary
    {
        $rango = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $totalEarned = (int) $driver->driverRides()
            ->where('status', RideStatus::Completed)
            ->whereBetween('completed_at', $rango)
            ->sum('final_fare');

        $completedRides = $driver->driverRides()
            ->where('status', RideStatus::Completed)
            ->whereBetween('completed_at', $rango)
            ->count();

        return new DriverEarningsSummary(
            from: $from,
            to: $to,
            currency: config('fares.currency'),
            totalEarned: $totalEarned,
            completedRides: $completedRides,
        );
    }
}
