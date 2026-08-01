<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;

/**
 * Cancela un viaje que un pasajero todavía no ha visto aceptado (historia #16).
 *
 * El viaje llega ya resuelto y ya validado: que siga en `requested` lo
 * garantiza `CancelRideRequest` antes de invocar esta Action, así que acá no
 * queda ninguna decisión de negocio, solo el cambio de estado.
 *
 * No hace falta tocar `driver_id` ni `active_passenger_id`: el viaje nunca
 * tuvo conductor asignado en este estado, y `active_passenger_id` es una
 * columna generada por la base que se recalcula sola al salir de los estados
 * activos (ver la migración de `rides`).
 */
final readonly class CancelRideAction
{
    public function handle(Ride $ride): Ride
    {
        $ride->update(['status' => RideStatus::Cancelled]);

        return $ride;
    }
}
