<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdsRequest;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/admin/ads
 *
 * Todos los anuncios, los que terminan más tarde primero.
 */
class ListAdsController extends Controller
{
    public function __invoke(ListAdsRequest $request): AnonymousResourceCollection
    {
        return AdResource::collection(Ad::query()->orderByDesc('ends_on')->orderByDesc('id')->get());
    }
}
