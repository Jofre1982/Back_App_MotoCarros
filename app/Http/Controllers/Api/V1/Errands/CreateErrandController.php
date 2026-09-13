<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Errands;

use App\Actions\Errands\CreateErrandAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Errands\CreateErrandRequest;
use App\Http\Resources\ErrandResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/errands
 *
 * Crea la solicitud de mandado del pasajero autenticado (historia #92). Nace
 * en `requested` y espera a que un conductor disponible para mandados lo
 * encuentre en `GET /errands` y lo acepte.
 */
class CreateErrandController extends Controller
{
    public function __invoke(
        CreateErrandRequest $request,
        CreateErrandAction $createErrand,
    ): JsonResponse {
        $errand = $createErrand->handle($request->user(), $request->toErrandRequest());

        return (new ErrandResource($errand))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
