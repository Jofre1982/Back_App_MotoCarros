<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Ride;

/**
 * Resultado de cancelar un viaje: el viaje ya `cancelled` junto con si esta
 * cancelación en particular generó una penalización por cancelación tardía
 * (historia #22).
 *
 * El cálculo y cobro efectivo del cargo, si aplica, quedan fuera de esta
 * historia: acá solo se determina si corresponde, igual que `RideEstimate`
 * solo estima sin cobrar.
 */
final readonly class RideCancellation
{
    public function __construct(
        public Ride $ride,
        public bool $feeApplies,
    ) {}
}
