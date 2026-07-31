<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Vehicles;

use App\Actions\Vehicles\UpdateVehicleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;

/**
 * PATCH /api/v1/me/vehicle
 *
 * Corrige los datos de la moto del conductor autenticado.
 *
 * Qué vehículo se toca no se decide acá: lo resuelve el Form Request por la
 * relación con la cuenta del token, que es también quien ya respondió el 403
 * del rol, el 404 de la cuenta sin moto y el 422 de la entrada. Para cuando
 * llega hasta este método no queda ninguna decisión pendiente.
 */
class UpdateVehicleController extends Controller
{
    public function __invoke(UpdateVehicleRequest $request, UpdateVehicleAction $updateVehicle): VehicleResource
    {
        $vehiculo = $updateVehicle->handle($request->vehicle(), $request->toUpdate());

        return new VehicleResource($vehiculo);
    }
}
