<?php

declare(strict_types=1);

namespace App\Services\Realtime;

/**
 * Implementación vigente de {@see RideParticipants} mientras no exista el
 * modelo `Ride` (historia #15).
 *
 * No conoce ningún viaje, así que el canal `ride.{id}` deniega todas las
 * suscripciones. Es deliberado: la infraestructura de tiempo real queda
 * completa y probada, y lo único que falta —la tabla de viajes— llega con la
 * historia que la crea. Fallar cerrado es lo correcto acá; abrir el canal
 * "provisionalmente" expondría la ubicación en vivo de conductores y
 * pasajeros a cualquier usuario autenticado que adivine un id.
 */
final class PendingRideParticipants implements RideParticipants
{
    /**
     * @return list<int>
     */
    public function forRide(int $rideId): array
    {
        return [];
    }
}
