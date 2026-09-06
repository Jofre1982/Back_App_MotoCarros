<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Fares\SetSiteFareAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetSiteFareRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;

/**
 * PUT /api/v1/admin/sites/{site}/fare
 *
 * Fija (o reemplaza) el precio de pasajero de un sitio para un tipo de
 * vehículo (historia técnica #85). El admin lo va a usar seguido —alza de
 * gasolina, demanda— así que es idempotente: mandar el mismo precio dos
 * veces no falla, solo lo confirma.
 *
 * El parámetro `Site $site` es lo que hace que Laravel resuelva el binding
 * implícito de la ruta, igual que `DriverDocument $document` en
 * `ApproveDriverDocumentController`.
 */
class SetSiteFareController extends Controller
{
    public function __invoke(
        SetSiteFareRequest $request,
        SetSiteFareAction $setSiteFare,
        Site $site,
    ): SiteResource {
        $setSiteFare->handle($site, $request->toUpdate());

        return new SiteResource($site->load('fares'));
    }
}
