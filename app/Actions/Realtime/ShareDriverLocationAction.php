<?php

declare(strict_types=1);

namespace App\Actions\Realtime;

use App\DTOs\Coordinates;
use App\Events\Realtime\DriverLocationUpdated;
use App\Models\Ride;
use Illuminate\Support\Facades\Date;

/**
 * Publica la ubicación del conductor asignado a un viaje en curso
 * (historia #20).
 *
 * El viaje llega resuelto y validado: que sea del conductor autenticado lo
 * garantiza `RidePolicy::shareLocation()` y que esté `in_progress` lo
 * garantiza `ShareRideLocationRequest`, así que acá no queda ninguna decisión
 * de negocio, solo publicar el evento — mismo reparto que `StartRideAction`.
 *
 * No escribe nada en `rides`: la ubicación no se persiste (fuera de alcance
 * de #20), solo se transmite en vivo por el canal del viaje.
 */
final readonly class ShareDriverLocationAction
{
    public function handle(Ride $ride, Coordinates $location): void
    {
        // La última posición queda guardada (además del evento en tiempo
        // real) para que el pasajero la lea al consultar el viaje, aunque
        // el WebSocket no conecte: `RideResource::driver_location`.
        $ride->driver?->driverProfile?->update([
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'location_updated_at' => Date::now(),
        ]);

        DriverLocationUpdated::dispatch(
            $ride->id,
            (int) $ride->driver_id,
            $location->latitude,
            $location->longitude,
        );
    }
}
