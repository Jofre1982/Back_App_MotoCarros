<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Fares\UpdateCargoJobTypePriceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCargoJobTypeRequest;
use App\Http\Resources\CargoJobTypeResource;
use App\Models\CargoJobType;

/**
 * PATCH /api/v1/admin/cargo-job-types/{cargoJobType}
 *
 * Ajusta el precio de un tipo de acarreo ya creado (historia técnica #86).
 */
class UpdateCargoJobTypeController extends Controller
{
    public function __invoke(
        UpdateCargoJobTypeRequest $request,
        UpdateCargoJobTypePriceAction $updatePrice,
        CargoJobType $cargoJobType,
    ): CargoJobTypeResource {
        $tipo = $updatePrice->handle($cargoJobType, $request->toPrice());

        return new CargoJobTypeResource($tipo);
    }
}
