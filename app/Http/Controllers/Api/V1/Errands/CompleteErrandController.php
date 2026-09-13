<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Errands;

use App\Actions\Errands\CompleteErrandAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Errands\CompleteErrandRequest;
use App\Http\Resources\ErrandResource;
use App\Models\Errand;

/**
 * POST /api/v1/errands/{errand}/complete
 *
 * Marca como completado un mandado que el conductor autenticado tiene
 * aceptado (historia #92).
 *
 * El parámetro `Errand $errand` es lo que hace que Laravel resuelva el
 * binding implícito de la ruta, mismo criterio que `CompleteRideController`.
 */
class CompleteErrandController extends Controller
{
    public function __invoke(
        CompleteErrandRequest $request,
        CompleteErrandAction $completeErrand,
        Errand $errand,
    ): ErrandResource {
        $errand = $completeErrand->handle($errand);

        return new ErrandResource($errand);
    }
}
