<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Support\Carbon;

/**
 * Lo que ganó un conductor en un rango de fechas: el total y cuántos viajes
 * completados lo componen (historia #30).
 *
 * `totalEarned` es la suma de `final_fare` de esos viajes, no un monto ya
 * liquidado: la transferencia real al conductor es un flujo aparte, fuera de
 * alcance de esta historia (ver el issue).
 */
final readonly class DriverEarningsSummary
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public string $currency,
        public int $totalEarned,
        public int $completedRides,
    ) {}
}
