<?php

declare(strict_types=1);

namespace App\Services\Realtime;

/**
 * Quiénes son los usuarios que participan de un viaje.
 *
 * Es lo único que necesita saber la autorización del canal `ride.{id}` para
 * decidir: la regla —solo el pasajero y el conductor asignado escuchan el
 * viaje— vive en App\Broadcasting\RideChannel, y de dónde salen esos ids vive
 * detrás de esta interfaz.
 *
 * Existe como interfaz para que el canal no dependa de Eloquent directamente.
 * La implementación registrada es {@see EloquentRideParticipants} desde la
 * historia #20 (ver el binding en AppServiceProvider).
 */
interface RideParticipants
{
    /**
     * Ids de los usuarios que participan del viaje: el pasajero que lo pidió y
     * el conductor asignado, si ya lo hay.
     *
     * Devuelve un arreglo vacío si el viaje no existe — un viaje inexistente y
     * uno del que nadie participa se autorizan igual (denegando), y así el
     * canal no filtra qué ids de viaje existen.
     *
     * @return list<int>
     */
    public function forRide(int $rideId): array;
}
