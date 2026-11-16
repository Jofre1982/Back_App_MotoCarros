<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ads\ListActiveAdsRequest;
use App\Http\Resources\PublicAdResource;
use App\Models\Ad;
use App\Services\Ads\AdSlots;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Date;

/**
 * GET /api/v1/ads
 *
 * Los anuncios que salen hoy, en orden aleatorio para que no salga siempre
 * el mismo de primero. Vacío si no hay ninguno: la app no muestra nada.
 */
class ListActiveAdsController extends Controller
{
    public function __invoke(ListActiveAdsRequest $request): AnonymousResourceCollection
    {
        $ads = Ad::query()
            ->showingOn(Date::now(Ad::TIMEZONE)->toDateString())
            ->inRandomOrder()
            ->limit(AdSlots::max())
            ->get();

        return PublicAdResource::collection($ads);
    }
}
