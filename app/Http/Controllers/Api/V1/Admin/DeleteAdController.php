<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Ads\DeleteAdAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteAdRequest;
use Illuminate\Http\Response;

/**
 * DELETE /api/v1/admin/ads/{ad}
 */
class DeleteAdController extends Controller
{
    public function __invoke(DeleteAdRequest $request, DeleteAdAction $deleteAd): Response
    {
        $deleteAd->handle($request->ad());

        return response()->noContent();
    }
}
