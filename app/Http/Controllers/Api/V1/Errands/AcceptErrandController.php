<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Errands;

use App\Actions\Errands\AcceptErrandAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Errands\AcceptErrandRequest;
use App\Http\Resources\ErrandResource;
use App\Models\Errand;

/**
 * POST /api/v1/errands/{errand}/accept
 *
 * Asigna al conductor autenticado como responsable del mandado, con el
 * precio que negoció con el pasajero (historia #92).
 *
 * El parámetro `Errand $errand` es lo que hace que Laravel resuelva el
 * binding implícito de la ruta, mismo criterio que `AcceptRideController`.
 */
class AcceptErrandController extends Controller
{
    public function __invoke(
        AcceptErrandRequest $request,
        AcceptErrandAction $acceptErrand,
        Errand $errand,
    ): ErrandResource {
        $errand = $acceptErrand->handle($errand, $request->user(), $request->agreedPrice());

        return new ErrandResource($errand);
    }
}
