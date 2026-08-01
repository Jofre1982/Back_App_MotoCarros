<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Actions\Payments\CalculateFareAction;
use App\DTOs\RideRequest;
use App\Enums\RideStatus;
use App\Exceptions\RouteEstimationFailed;
use App\Models\Ride;
use App\Models\User;
use App\Services\Maps\RouteEstimator;

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
 * El aviso a los conductores cercanos es de la historia #17; acá el viaje solo
 * queda disponible.
 */
final readonly class CreateRideAction
{
    public function __construct(
        private RouteEstimator $routeEstimator,
        private CalculateFareAction $calculateFare,
    ) {}

    /**
     * @throws RouteEstimationFailed si el proveedor de mapas no encuentra una
     *                               ruta entre el origen y el destino.
     */
    public function handle(User $passenger, RideRequest $request): Ride
    {
        $route = $this->routeEstimator->estimate($request->origin, $request->destination);
        $fare = $this->calculateFare->handle($route);

        return Ride::create([
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
    }
}
