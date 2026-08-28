<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Vehicles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\ShowVehicleRequest;
use App\Http\Resources\VehicleResource;

/**
 * GET /api/v1/me/vehicle
 *
 * Devuelve la moto ya registrada del conductor autenticado.
 *
 * Qué vehículo se muestra no se decide acá: lo resuelve el Form Request por
 * la relación con la cuenta del token, que es también quien ya respondió el
 * 403 del rol y el 404 de la cuenta sin moto. No hay Action detrás y es a
 * propósito, mismo criterio que `ShowProfileController`: leer un recurso ya
 * resuelto no es un caso de uso de negocio, no decide nada y no cambia nada.
 */
class ShowVehicleController extends Controller
{
    public function __invoke(ShowVehicleRequest $request): VehicleResource
    {
        return new VehicleResource($request->vehicle());
    }
}
