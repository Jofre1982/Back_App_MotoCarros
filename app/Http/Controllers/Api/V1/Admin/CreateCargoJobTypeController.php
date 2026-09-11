<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Fares\CreateCargoJobTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateCargoJobTypeRequest;
use App\Http\Resources\CargoJobTypeResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/admin/cargo-job-types
 *
 * Crea un tipo de acarreo con su precio fijo (historia técnica #86).
 */
class CreateCargoJobTypeController extends Controller
{
    public function __invoke(
        CreateCargoJobTypeRequest $request,
        CreateCargoJobTypeAction $createCargoJobType,
    ): JsonResponse {
        $tipo = $createCargoJobType->handle($request->toName(), $request->toPrice());

        return (new CargoJobTypeResource($tipo))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
