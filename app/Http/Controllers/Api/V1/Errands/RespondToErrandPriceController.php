<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Errands;

use App\Actions\Errands\RespondToErrandPriceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Errands\RespondToErrandPriceRequest;
use App\Http\Resources\ErrandResource;
use App\Models\Errand;

class RespondToErrandPriceController extends Controller
{
    public function __invoke(RespondToErrandPriceRequest $request, RespondToErrandPriceAction $respond, Errand $errand): ErrandResource
    {
        return new ErrandResource($respond->handle($errand, $request->acceptsPrice())->load('driver', 'destinationSite'));
    }
}
