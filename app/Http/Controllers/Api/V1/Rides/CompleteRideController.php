<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Rides\CompleteRideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\CompleteRideRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/rides/{ride}/complete
 *
 * Marca el viaje como completado (historia #24). Que el conductor autenticado
 * sea el asignado lo resuelve `RidePolicy::complete()` desde
 * `CompleteRideRequest`, y que el viaje siga en `in_progress`, el propio Form
 * Request.
 *
 * El parámetro `Ride $ride` es lo que hace que Laravel resuelva el binding
 * implícito de la ruta, igual que en `StartRideController`.
 */
class CompleteRideController extends Controller
{
    public function __invoke(
        CompleteRideRequest $request,
        CompleteRideAction $completeRide,
        Ride $ride,
    ): JsonResponse {
        $ride = $completeRide->handle($ride);

        return (new RideResource($ride))->response();
    }
}
