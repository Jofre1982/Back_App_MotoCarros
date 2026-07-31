<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Payments\CalculateFareAction;
use App\DTOs\RideEstimate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\EstimateRideRequest;
use App\Http\Resources\RideEstimateResource;
use App\Services\Maps\RouteEstimator;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/rides/estimate
 *
 * Le muestra al pasajero cuánto costaría un viaje antes de solicitarlo. No
 * crea nada: el modelo `Ride` todavía no existe (historia #15), así que esto
 * es solo una consulta contra el proveedor de mapas y el motor de tarifa, con
 * la misma fórmula que se usa al cobrar el viaje real (ver CalculateFareAction).
 */
class EstimateRideController extends Controller
{
    public function __invoke(
        EstimateRideRequest $request,
        RouteEstimator $routeEstimator,
        CalculateFareAction $calculateFare,
    ): JsonResponse {
        $route = $routeEstimator->estimate($request->origin(), $request->destination());

        $estimate = new RideEstimate($route, $calculateFare->handle($route));

        return (new RideEstimateResource($estimate))->response();
    }
}
