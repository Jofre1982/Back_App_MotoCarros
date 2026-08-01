<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Ride;

/**
 * Resultado de `POST /rides/{id}/cancel`: el viaje ya `cancelled` (si canceló
 * el pasajero) o de vuelta en `requested` sin conductor (si lo devolvió el
 * conductor asignado, historia #23), junto con si esta cancelación en
 * particular generó una penalización por cancelación tardía (historia #22).
 * `feeApplies` siempre es `false` cuando quien cancela es el conductor: ese
 * caso no cancela el viaje ni genera cargo.
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
