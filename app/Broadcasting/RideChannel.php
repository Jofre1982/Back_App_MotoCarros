<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;
use App\Services\Realtime\RideParticipants;

/**
 * Canal privado `ride.{rideId}`: todo lo que pasa dentro de un viaje.
 *
 * Cambios de estado y ubicación del conductor en curso viajan por acá, así que
 * lo escuchan exactamente las dos personas que están en ese viaje: el pasajero
 * que lo pidió y el conductor asignado.
 *
 * De dónde salen esos dos ids es problema de {@see RideParticipants}, no de
 * este canal — el modelo `Ride` llega con la historia #15 y hasta entonces la
 * implementación registrada no reconoce ningún viaje, con lo que este canal
 * deniega todo. La regla de autorización, en cambio, ya es la definitiva.
 */
final readonly class RideChannel
{
    public function __construct(private RideParticipants $participants) {}

    /**
     * @param  string  $rideId  Llega como string desde el nombre del canal.
     */
    public function join(User $user, string $rideId): bool
    {
        // El id viene del nombre del canal, que lo elige el cliente: cualquier
        // cosa que no sea un entero positivo se rechaza antes de convertirla,
        // en vez de dejar que un "12abc" se transforme en el viaje 12.
        if (! ctype_digit($rideId)) {
            return false;
        }

        return in_array(
            (int) $user->getKey(),
            $this->participants->forRide((int) $rideId),
            strict: true,
        );
    }
}
