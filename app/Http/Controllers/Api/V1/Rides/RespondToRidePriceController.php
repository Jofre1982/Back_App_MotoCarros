<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Rides\RespondToRidePriceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\RespondToRidePriceRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;

class RespondToRidePriceController extends Controller
{
    public function __invoke(RespondToRidePriceRequest $request, RespondToRidePriceAction $respond, Ride $ride): RideResource
    {
        return new RideResource($respond->handle($ride, $request->acceptsPrice())->load('driver', 'destinationSite'));
    }
}
