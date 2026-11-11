<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Ads\CreateAdAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdRequest;
use App\Http\Resources\AdResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/admin/ads
 */
class CreateAdController extends Controller
{
    public function __invoke(CreateAdRequest $request, CreateAdAction $createAd): JsonResponse
    {
        $ad = $createAd->handle($request->toAttributes(), $request->image());

        return (new AdResource($ad))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
