<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Rides\AcceptRideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\AcceptRideRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/rides/{ride}/accept
 *
 * Asigna el viaje al conductor autenticado (historia #18). El permiso (rol
 * conductor) y que no tenga ya un viaje propio activo los resuelve
 * `AcceptRideRequest`; que el viaje siga disponible lo resuelve la Action bajo
 * lock, porque ahí es donde el estado puede cambiar entre que se resolvió el
 * binding de la ruta y que se ejecuta esta petición.
 *
 * El parámetro `Ride $ride` es lo que hace que Laravel resuelva el binding
 * implícito de la ruta, igual que en `CancelRideController`.
 */
class AcceptRideController extends Controller
{
    public function __invoke(
        AcceptRideRequest $request,
        AcceptRideAction $acceptRide,
        Ride $ride,
    ): JsonResponse {
        $ride = $acceptRide->handle($ride, $request->user());

        return (new RideResource($ride))->response();
    }
}
