<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Actions\Ads\RecordAdViewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ads\ViewAdRequest;
use Illuminate\Http\Response;

/**
 * POST /api/v1/ads/{ad}/view
 */
class RecordAdViewController extends Controller
{
    public function __invoke(ViewAdRequest $request, RecordAdViewAction $recordView): Response
    {
        $recordView->handle($request->ad());

        return response()->noContent();
    }
}
