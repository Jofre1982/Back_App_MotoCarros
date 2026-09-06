<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListCargoJobTypesRequest;
use App\Http\Resources\CargoJobTypeResource;
use App\Models\CargoJobType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/admin/cargo-job-types
 *
 * Lista el catálogo de tipos de acarreo de Motocarga con su precio, para
 * que el admin lo gestione desde el panel (historia técnica #86).
 */
class ListCargoJobTypesController extends Controller
{
    public function __invoke(ListCargoJobTypesRequest $request): AnonymousResourceCollection
    {
        $tipos = CargoJobType::query()->orderBy('name')->get();

        return CargoJobTypeResource::collection($tipos);
    }
}
