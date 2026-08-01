<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Actions\Payments\CalculateFareAction;
use App\DTOs\Coordinates;
use App\DTOs\RideRequest;
use App\Enums\RideStatus;
use App\Events\Realtime\RideRequested;
use App\Exceptions\RouteEstimationFailed;
use App\Models\Ride;
use App\Models\User;
use App\Services\Maps\RouteEstimator;
use App\Services\Realtime\NearbyDriverFinder;

/**
 * Crea la solicitud de viaje de un pasajero.
 *
 * El pasajero llega como parámetro y no dentro del DTO: es lo que garantiza que
 * el viaje sea siempre de quien invoca el caso de uso (el usuario que resolvió
 * el guard) y nunca de lo que venga en la entrada.
 *
 * El trayecto y la tarifa se calculan acá y se guardan con el viaje, con el
 * mismo estimador y el mismo motor que usa `POST /rides/estimate`: si cada lado
 * calculara lo suyo, el pasajero podría ver un número antes de pedir el viaje y
 * otro después de pedirlo. Quedan guardados en vez de recalcularse al consultar
 * el viaje porque es el número que el pasajero aceptó —y porque cada consulta al
 * proveedor de mapas se paga.
 *
 * No hay transacción: se escribe una sola fila. Y si el proveedor no entrega una
 * ruta, la excepción sale antes del INSERT, así que tampoco queda un viaje sin
 * tarifa —que además dejaría al pasajero con un activo que no llegó a pedir.
 *
 * Al final avisa a los conductores cercanos disponibles (historia #17): quién
 * es "cercano" lo resuelve `NearbyDriverFinder`, y si no hay ninguno no se
 * dispara ningún evento —un `RideRequested` sin conductores a quién llegarle
 * no le sirve a nadie.
 */
final readonly class CreateRideAction
{
    public function __construct(
        private RouteEstimator $routeEstimator,
        private CalculateFareAction $calculateFare,
        private NearbyDriverFinder $nearbyDrivers,
    ) {}

    /**
     * @throws RouteEstimationFailed si el proveedor de mapas no encuentra una
     *                               ruta entre el origen y el destino.
     */
    public function handle(User $passenger, RideRequest $request): Ride
    {
        $route = $this->routeEstimator->estimate($request->origin, $request->destination);
        $fare = $this->calculateFare->handle($route);

        $ride = Ride::create([
            'passenger_id' => $passenger->getKey(),
            'status' => RideStatus::Requested,
            'origin_latitude' => $request->origin->latitude,
            'origin_longitude' => $request->origin->longitude,
            'destination_latitude' => $request->destination->latitude,
            'destination_longitude' => $request->destination->longitude,
            'estimated_distance_meters' => $route->distanceMeters,
            'estimated_duration_seconds' => $route->durationSeconds,
            'currency' => $fare->currency,
            'estimated_fare' => $fare->total,
        ]);

        $this->notifyNearbyDrivers($ride, $request->origin);

        return $ride;
    }

    private function notifyNearbyDrivers(Ride $ride, Coordinates $origin): void
    {
        $driverIds = $this->nearbyDrivers->near($origin);

        if ($driverIds === []) {
            return;
        }

        RideRequested::dispatch(
            rideId: $ride->id,
            originLatitude: $ride->origin_latitude,
            originLongitude: $ride->origin_longitude,
            destinationLatitude: $ride->destination_latitude,
            destinationLongitude: $ride->destination_longitude,
            currency: $ride->currency,
            estimatedFare: $ride->estimated_fare,
            nearbyDriverIds: $driverIds,
        );
    }
}
