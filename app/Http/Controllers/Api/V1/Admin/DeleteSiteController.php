<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Fares\DeleteSiteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteSiteRequest;
use App\Models\Site;
use Illuminate\Http\Response;

/**
 * DELETE /api/v1/admin/sites/{site}
 *
 * Borra un sitio del catálogo, con sus precios (historia técnica #85).
 * Responde 204 sin cuerpo: no queda ningún recurso que devolver, mismo
 * criterio que el cierre de sesión (ver .claude/STANDARDS.md, "Envelope de
 * las respuestas").
 */
class DeleteSiteController extends Controller
{
    public function __invoke(
        DeleteSiteRequest $request,
        DeleteSiteAction $deleteSite,
        Site $site,
    ): Response {
        $deleteSite->handle($site);

        return response()->noContent();
    }
}
