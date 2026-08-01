<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Rides\RateDriverAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\RateDriverRequest;
use App\Http\Resources\RideRatingResource;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/rides/{ride}/rate-driver
 *
 * Registra la calificación del pasajero al conductor de un viaje completado
 * (historia #27). Que el pasajero autenticado sea el dueño del viaje lo
 * resuelve `RidePolicy::rateDriver()` desde `RateDriverRequest`, y que el
 * viaje esté `completed` sin calificación previa, el propio Form Request.
 */
class RateDriverController extends Controller
{
    public function __invoke(
        RateDriverRequest $request,
        RateDriverAction $rateDriver,
        Ride $ride,
    ): JsonResponse {
        $rating = $rateDriver->handle($ride, $request->score(), $request->comment());

        return (new RideRatingResource($rating))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
