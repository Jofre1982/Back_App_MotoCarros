<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Fares\CreateSiteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateSiteRequest;
use App\Http\Resources\SiteResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/admin/sites
 *
 * Crea un sitio nuevo en el catálogo (historia técnica #85). Nace sin
 * precios: el admin los fija aparte con `PUT /admin/sites/{site}/fare`.
 */
class CreateSiteController extends Controller
{
    public function __invoke(
        CreateSiteRequest $request,
        CreateSiteAction $createSite,
    ): JsonResponse {
        $site = $createSite->handle($request->toName());

        return (new SiteResource($site->load('fares')))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
