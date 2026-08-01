<?php

declare(strict_types=1);

namespace App\Events\Realtime;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un viaje que ya se aceptó deja de estar disponible para los demás
 * conductores que lo habían recibido por `RideRequested` (historia #17).
 *
 * A quién avisar se resuelve igual que en `RideRequested` —conductor
 * disponible dentro del radio configurado alrededor del origen— pero
 * calculado de nuevo en el momento de aceptar, y no reutilizando la lista
 * original: no queda persistida en ningún lado (ver `AcceptRideAction`). Un
 * conductor que se desconectó mientras tanto no necesita el aviso, y uno que
 * se conectó después nunca vio la solicitud, así que recibir este evento
 * sobre un viaje que no conoce no tiene efecto en el cliente.
 */
final class RideNoLongerAvailable implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  list<int>  $driverIds
     */
    public function __construct(
        public readonly int $rideId,
        public readonly array $driverIds,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            static fn (int $driverId): PrivateChannel => new PrivateChannel("driver.{$driverId}"),
            $this->driverIds,
        );
    }

    public function broadcastAs(): string
    {
        return 'ride.unavailable';
    }

    /**
     * @return array{ride_id: int}
     */
    public function broadcastWith(): array
    {
        return ['ride_id' => $this->rideId];
    }
}
