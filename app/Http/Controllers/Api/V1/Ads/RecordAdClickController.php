<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Actions\Ads\RecordAdClickAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ads\ViewAdRequest;
use Illuminate\Http\Response;

/**
 * POST /api/v1/ads/{ad}/click
 */
class RecordAdClickController extends Controller
{
    public function __invoke(ViewAdRequest $request, RecordAdClickAction $recordClick): Response
    {
        $recordClick->handle($request->ad());

        return response()->noContent();
    }
}
