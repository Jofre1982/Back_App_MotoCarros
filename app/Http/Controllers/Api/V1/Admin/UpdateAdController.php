<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Ads\UpdateAdAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdRequest;
use App\Http\Resources\AdResource;

/**
 * PATCH /api/v1/admin/ads/{ad}
 */
class UpdateAdController extends Controller
{
    public function __invoke(UpdateAdRequest $request, UpdateAdAction $updateAd): AdResource
    {
        return new AdResource($updateAd->handle($request->ad(), $request->toAttributes()));
    }
}
