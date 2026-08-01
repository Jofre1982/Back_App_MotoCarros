<?php

declare(strict_types=1);

namespace App\Events\Realtime;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * La posición actual del conductor durante un viaje en curso (historia #20).
 *
 * Va al canal del viaje —no al del conductor— porque quien la escucha es el
 * pasajero siguiendo *ese* viaje (historia #21); `RideChannel` ya resuelve
 * que solo el pasajero y el conductor asignado entran ahí.
 *
 * Es `ShouldBroadcast` (encolado) y no `ShouldBroadcastNow` como
 * `RealtimePingSent`: ese era una prueba de concepto sin cola, este es el
 * primer evento de negocio real y sigue el criterio general de
 * .claude/STANDARDS.md de no retrasar la respuesta HTTP con la llamada al
 * servidor de Reverb.
 *
 * No persiste nada: fuera de alcance de #20 es guardar un historial de
 * coordenadas, así que esta clase solo transporta el dato hasta el
 * broadcaster.
 */
final class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $rideId,
        public readonly int $driverId,
        public readonly float $latitude,
        public readonly float $longitude,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("ride.{$this->rideId}");
    }

    /**
     * Nombre con el que el cliente escucha el evento. Explícito para no atar
     * la app móvil al FQCN de una clase de PHP (mismo criterio que
     * `RealtimePingSent`).
     */
    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    /**
     * @return array{driver_id: int, latitude: float, longitude: float}
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
