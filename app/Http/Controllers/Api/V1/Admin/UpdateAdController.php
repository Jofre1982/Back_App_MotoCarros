<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Ads\UpdateAdAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdRequest;
use App\Http\Resources\AdResource;
use App\Models\Ad;

/**
 * PATCH /api/v1/admin/ads/{ad}
 */
class UpdateAdController extends Controller
{
    public function __invoke(UpdateAdRequest $request, UpdateAdAction $updateAd, Ad $ad): AdResource
    {
        return new AdResource($updateAd->handle($ad, $request->toAttributes()));
    }
}
