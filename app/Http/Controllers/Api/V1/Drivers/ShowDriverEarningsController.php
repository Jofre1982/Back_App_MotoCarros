<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Drivers;

use App\Actions\Drivers\SummarizeDriverEarningsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drivers\ShowDriverEarningsRequest;
use App\Http\Resources\DriverEarningsSummaryResource;

/**
 * GET /api/v1/me/earnings
 *
 * Resumen de lo que ganó el conductor autenticado en un rango de fechas:
 * total y número de viajes completados (historia #30). Complementa
 * `GET /me/rides`, que trae el detalle viaje por viaje; este endpoint no
 * liquida ni transfiere nada, solo lee.
 */
class ShowDriverEarningsController extends Controller
{
    public function __invoke(
        ShowDriverEarningsRequest $request,
        SummarizeDriverEarningsAction $summarize,
    ): DriverEarningsSummaryResource {
        $resumen = $summarize->handle($request->user(), $request->from(), $request->to());

        return new DriverEarningsSummaryResource($resumen);
    }
}
