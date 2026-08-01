<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Rides\CancelRideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\CancelRideRequest;
use App\Http\Resources\RideCancellationResource;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/rides/{ride}/cancel
 *
 * Cancela el viaje, ya sea a pedido del pasajero dueño —siga en `requested`
 * (historia #16) o ya lo haya aceptado un conductor (historia #22)— o del
 * conductor asignado, que en cambio lo devuelve al pool (historia #23). Cuál
 * de los dos es y si corresponde ya lo resolvió `CancelRideRequest`; acá no
 * queda ninguna decisión, solo pasarle el actor a la Action para que elija la
 * rama.
 *
 * El parámetro `Ride $ride` es lo que hace que Laravel resuelva el binding
 * implícito de la ruta (`{ride}`): la reflexión de esta firma es lo que usa
 * `SubstituteBindings` para saber qué modelo cargar, y corre antes de que se
 * resuelva `CancelRideRequest`, así que `authorize()` ya encuentra el modelo
 * y no el id crudo.
 */
class CancelRideController extends Controller
{
    public function __invoke(
        CancelRideRequest $request,
        CancelRideAction $cancelRide,
        Ride $ride,
    ): JsonResponse {
        $cancellation = $cancelRide->handle($ride, $request->user());

        return (new RideCancellationResource($cancellation))->response();
    }
}
