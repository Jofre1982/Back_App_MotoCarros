<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Rides\StartRideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\StartRideRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/rides/{ride}/start
 *
 * Marca el viaje como iniciado (historia #19). Que el conductor autenticado
 * sea el asignado lo resuelve `RidePolicy::start()` desde `StartRideRequest`,
 * y que el viaje siga en `accepted`, el propio Form Request.
 *
 * El parámetro `Ride $ride` es lo que hace que Laravel resuelva el binding
 * implícito de la ruta, igual que en `AcceptRideController`.
 */
class StartRideController extends Controller
{
    public function __invoke(
        StartRideRequest $request,
        StartRideAction $startRide,
        Ride $ride,
    ): JsonResponse {
        $ride = $startRide->handle($ride);

        return (new RideResource($ride))->response();
    }
}
