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
 * Existe como interfaz porque el modelo `Ride` todavía no está implementado
 * (llega con la historia #15, "Solicitar un viaje"). Mientras tanto la
 * implementación registrada es {@see PendingRideParticipants}, que no conoce
 * ningún viaje y por lo tanto no deja entrar a nadie. Cuando exista la tabla,
 * cambia el binding en AppServiceProvider y el canal empieza a autorizar sin
 * tocar routes/channels.php ni el canal.
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
