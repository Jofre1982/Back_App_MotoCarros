<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Fares\DeleteCargoJobTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteCargoJobTypeRequest;
use App\Models\CargoJobType;
use Illuminate\Http\Response;

/**
 * DELETE /api/v1/admin/cargo-job-types/{cargoJobType}
 *
 * Borra un tipo de acarreo del catálogo (historia técnica #86). Responde
 * 204 sin cuerpo, mismo criterio que borrar un sitio (#85).
 */
class DeleteCargoJobTypeController extends Controller
{
    public function __invoke(
        DeleteCargoJobTypeRequest $request,
        DeleteCargoJobTypeAction $deleteCargoJobType,
        CargoJobType $cargoJobType,
    ): Response {
        $deleteCargoJobType->handle($cargoJobType);

        return response()->noContent();
    }
}
