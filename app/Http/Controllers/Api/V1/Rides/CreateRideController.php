<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Rides\CreateRideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\CreateRideRequest;
use App\Http\Resources\RideResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/rides
 *
 * Crea la solicitud de viaje del pasajero autenticado. El viaje nace en
 * `requested` y queda esperando a que un conductor lo acepte (historia #18):
 * esta API no asigna conductor por su cuenta.
 *
 * El pasajero sale del guard y no de la entrada, igual que el dueño del
 * vehículo. La autorización (solo pasajeros) la resuelve `RidePolicy` desde el
 * Form Request, así que acá no queda ninguna decisión.
 */
class CreateRideController extends Controller
{
    public function __invoke(
        CreateRideRequest $request,
        CreateRideAction $createRide,
    ): JsonResponse {
        $ride = $createRide->handle($request->user(), $request->toRideRequest());

        return (new RideResource($ride))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
