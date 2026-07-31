<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Vehicles;

use App\Actions\Vehicles\RegisterVehicleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\RegisterVehicleRequest;
use App\Http\Resources\VehicleResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/me/vehicle
 *
 * Da de alta la moto del conductor autenticado, que es lo último que le falta
 * para poder recibir solicitudes de viaje.
 *
 * El dueño sale del guard y no de la entrada: es el mismo criterio que el rol en
 * los endpoints de registro. La autorización (solo conductores) la resuelve
 * `VehiclePolicy` desde el Form Request, así que acá no queda ninguna decisión.
 */
class RegisterVehicleController extends Controller
{
    public function __invoke(
        RegisterVehicleRequest $request,
        RegisterVehicleAction $registerVehicle,
    ): JsonResponse {
        $vehicle = $registerVehicle->handle($request->user(), $request->toRegistration());

        return (new VehicleResource($vehicle))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
