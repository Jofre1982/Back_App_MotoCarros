<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListSitesRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/admin/sites
 *
 * Lista el catálogo de sitios con sus precios, para que el admin lo
 * administre desde el panel (historia técnica #85).
 */
class ListSitesController extends Controller
{
    public function __invoke(ListSitesRequest $request): AnonymousResourceCollection
    {
        $sites = Site::query()->with('fares')->orderBy('name')->get();

        return SiteResource::collection($sites);
    }
}
