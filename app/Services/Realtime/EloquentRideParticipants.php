<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use App\Models\Ride;

/**
 * Implementación vigente de {@see RideParticipants} desde la historia #20.
 *
 * Reemplaza a `PendingRideParticipants` ahora que el tracking en tiempo real
 * (#20 comparte la ubicación, #21 la consume) necesita que el canal
 * `ride.{id}` autorice de verdad al pasajero y al conductor asignado.
 */
final readonly class EloquentRideParticipants implements RideParticipants
{
    /**
     * @return list<int>
     */
    public function forRide(int $rideId): array
    {
        $ride = Ride::query()->find($rideId);

        if ($ride === null) {
            return [];
        }

        return array_filter(
            [$ride->passenger_id, $ride->driver_id],
            static fn (?int $id): bool => $id !== null,
        );
    }
}
