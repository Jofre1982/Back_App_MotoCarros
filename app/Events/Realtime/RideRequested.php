<?php

declare(strict_types=1);

namespace App\Events\Realtime;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un viaje nuevo, disponible para los conductores disponibles cerca del
 * origen (historia #17).
 *
 * A diferencia de `DriverLocationUpdated`, que va a un solo canal (el del
 * viaje), este evento va a un canal `driver.{id}` por cada conductor
 * cercano: quién es "cercano" ya lo resolvió `NearbyDriverFinder` antes de
 * construir el evento, así que acá no queda ninguna consulta pendiente —
 * mismo criterio que el resto de los eventos de negocio (ver
 * .claude/STANDARDS.md).
 *
 * El destino va como sitio (id y nombre) desde la historia #87, no como
 * coordenadas: el conductor ya conoce los sitios del pueblo, y el nombre le
 * dice más de un vistazo que un par de números. Lleva el nombre además del
 * id para que el conductor no tenga que resolverlo con una consulta aparte
 * al recibir el evento.
 *
 * No lleva ningún dato del pasajero: un conductor decide si acepta por el
 * destino y la tarifa, no por quién lo pide.
 */
final class RideRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  list<int>  $nearbyDriverIds
     */
    public function __construct(
        public readonly int $rideId,
        public readonly float $originLatitude,
        public readonly float $originLongitude,
        public readonly int $destinationSiteId,
        public readonly string $destinationSiteName,
        public readonly int $passengerCount,
        public readonly string $currency,
        public readonly int $estimatedFare,
        public readonly array $nearbyDriverIds,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            static fn (int $driverId): PrivateChannel => new PrivateChannel("driver.{$driverId}"),
            $this->nearbyDriverIds,
        );
    }

    /**
     * Nombre con el que el cliente escucha el evento. Explícito para no atar
     * la app móvil al FQCN de una clase de PHP (mismo criterio que
     * `DriverLocationUpdated`).
     */
    public function broadcastAs(): string
    {
        return 'ride.requested';
    }

    /**
     * @return array{
     *     ride_id: int,
     *     origin: array{latitude: float, longitude: float},
     *     destination: array{site_id: int, name: string},
     *     passenger_count: int,
     *     currency: string,
     *     estimated_fare: int,
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->rideId,
            'origin' => [
                'latitude' => $this->originLatitude,
                'longitude' => $this->originLongitude,
            ],
            'destination' => [
                'site_id' => $this->destinationSiteId,
                'name' => $this->destinationSiteName,
            ],
            'passenger_count' => $this->passengerCount,
            'currency' => $this->currency,
            'estimated_fare' => $this->estimatedFare,
        ];
    }
}
