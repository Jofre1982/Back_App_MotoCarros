<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Payments\CalculateSiteFareAction;
use App\DTOs\RideEstimate;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\EstimateRideRequest;
use App\Http\Resources\RideEstimateResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/rides/estimate
 *
 * Le muestra al pasajero cuánto costaría un viaje antes de solicitarlo. No
 * crea nada: el modelo `Ride` todavía no existe (historia #15), así que esto
 * es solo una consulta contra el precio fijo del sitio elegido, con el mismo
 * motor que se usa al cobrar el viaje real (historia #87,
 * `CalculateSiteFareAction`).
 */
class EstimateRideController extends Controller
{
    public function __invoke(
        EstimateRideRequest $request,
        CalculateSiteFareAction $calculateFare,
    ): JsonResponse {
        $site = $request->destinationSite();
        $fare = $calculateFare->handle($site, VehicleType::Motocarro, $request->passengerCount());

        $estimate = new RideEstimate(
            destinationSite: $site,
            passengerCount: $request->passengerCount(),
            currency: $fare->currency,
            fare: $fare->total,
        );

        return (new RideEstimateResource($estimate))->response();
    }
}
