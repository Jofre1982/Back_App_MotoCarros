<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Enums\RideStatus;
use App\Events\Realtime\RideStatusChanged;
use App\Models\Ride;

/**
 * Marca como iniciado un viaje que el conductor asignado ya había aceptado
 * (historia #19).
 *
 * El viaje llega resuelto y validado: que sea del conductor autenticado lo
 * garantiza `RidePolicy::start()` y que siga en `accepted` lo garantiza
 * `StartRideRequest`, así que acá no queda ninguna decisión de negocio, solo
 * la transición. Mismo reparto que en `CancelRideAction`.
 *
 * No relee el viaje bajo lock como hace `AcceptRideAction`, y la diferencia es
 * de quién compite por la fila: allá son dos conductores distintos por un
 * viaje libre, y perder la carrera en silencio reasignaría el viaje de otro.
 * Acá el único que puede llegar a la vez es el mismo conductor tocando el
 * botón dos veces, y lo peor que produce esa carrera es que `started_at` se
 * reescriba con una marca de tiempo unos milisegundos posterior; el segundo
 * toque, una vez confirmada la primera transacción, ya sale como 422.
 *
 * `active_driver_id` no se toca: es una columna generada y `in_progress` sigue
 * siendo un estado activo, así que el viaje continúa ocupando al conductor
 * (ver la migración de `rides`).
 */
final readonly class StartRideAction
{
    public function handle(Ride $ride): Ride
    {
        $ride->update([
            'status' => RideStatus::InProgress,
            'started_at' => now(),
        ]);

        // El pasajero está esperando en el canal del viaje desde que lo
        // aceptaron: que arrancó es justo lo que cambia su pantalla, y desde
        // acá empieza además a llegarle la ubicación del conductor
        // (historias #20 y #21).
        RideStatusChanged::dispatch($ride->id, $ride->status, $ride->driver_id);

        return $ride;
    }
}
