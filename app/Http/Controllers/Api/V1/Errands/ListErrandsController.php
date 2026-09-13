<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Errands;

use App\Enums\ErrandStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Errands\ListErrandsRequest;
use App\Http\Resources\ErrandResource;
use App\Models\Errand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/errands
 *
 * Lista los mandados `requested` disponibles para que un conductor los
 * acepte (historia #92). Un conductor que no se marcó disponible **para
 * mandados** (pool separado del de viajes) recibe una lista vacía en vez de
 * un error: tiene el permiso del rol, solo que no optó por ver esta cola.
 */
class ListErrandsController extends Controller
{
    public function __invoke(ListErrandsRequest $request): AnonymousResourceCollection
    {
        if (! $request->isAvailableForErrands()) {
            return ErrandResource::collection([]);
        }

        $errands = Errand::query()
            ->where('status', ErrandStatus::Requested)
            ->with('destinationSite')
            ->orderBy('created_at')
            ->get();

        return ErrandResource::collection($errands);
    }
}
