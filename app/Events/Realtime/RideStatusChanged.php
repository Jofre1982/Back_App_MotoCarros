<?php

declare(strict_types=1);

namespace App\Events\Realtime;

use App\Enums\RideStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una transición del ciclo de vida del viaje (historia #21).
 *
 * Va al canal del viaje, igual que {@see DriverLocationUpdated}: quien
 * necesita enterarse es el pasajero que está esperando —y el conductor
 * asignado, que ya está ahí—, y `RideChannel` resuelve que no entre nadie
 * más.
 *
 * Lleva el estado **nuevo** y el conductor asignado, no el anterior: el
 * cliente ya sabe en qué estado tenía el viaje, y lo que necesita para
 * repintar la pantalla es a dónde pasó. Un viaje sin conductor asignado manda
 * `driver_id: null` en vez de omitir el campo, mismo criterio que el `driver`
 * de `GET /rides/{id}`.
 *
 * Es `ShouldBroadcast` (encolado), como el resto de los eventos de negocio:
 * la respuesta HTTP de aceptar o iniciar no espera al servidor de Reverb (ver
 * .claude/STANDARDS.md).
 */
final class RideStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $rideId,
        public readonly RideStatus $status,
        public readonly ?int $driverId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("ride.{$this->rideId}");
    }

    /**
     * Nombre con el que el cliente escucha el evento. Explícito para no atar
     * la app móvil al FQCN de una clase de PHP (mismo criterio que
     * `DriverLocationUpdated`).
     */
    public function broadcastAs(): string
    {
        return 'status.changed';
    }

    /**
     * @return array{status: string, driver_id: int|null}
     */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->status->value,
            'driver_id' => $this->driverId,
        ];
    }
}
