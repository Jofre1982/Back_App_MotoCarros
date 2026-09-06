<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sites;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\ListSitesRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/sites
 *
 * Lista el catálogo de sitios con sus precios para que el pasajero elija
 * destino al pedir un viaje (historia #87). De solo lectura: crear/editar
 * sitios y precios sigue siendo exclusivo del admin
 * (`Admin\ListSitesController`, historia #85).
 */
class ListSitesController extends Controller
{
    public function __invoke(ListSitesRequest $request): AnonymousResourceCollection
    {
        $sites = Site::query()->with('fares')->orderBy('name')->get();

        return SiteResource::collection($sites);
    }
}
