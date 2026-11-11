<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Ads\ReplaceAdImageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplaceAdImageRequest;
use App\Http\Resources\AdResource;

/**
 * POST /api/v1/admin/ads/{ad}/image
 */
class ReplaceAdImageController extends Controller
{
    public function __invoke(ReplaceAdImageRequest $request, ReplaceAdImageAction $replaceImage): AdResource
    {
        return new AdResource($replaceImage->handle($request->ad(), $request->image()));
    }
}
