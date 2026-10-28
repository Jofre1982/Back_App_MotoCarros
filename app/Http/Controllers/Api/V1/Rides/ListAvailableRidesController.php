<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Enums\RideStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\ListAvailableRidesRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListAvailableRidesController extends Controller
{
    public function __invoke(ListAvailableRidesRequest $request): AnonymousResourceCollection
    {
        if ($request->drivesMotocarga()) {
            return RideResource::collection([]);
        }

        $rides = Ride::query()
            ->where('status', RideStatus::Requested)
            ->whereNull('driver_id')
            ->where('passenger_id', '!=', $request->user()?->getKey())
            ->with('driver', 'destinationSite')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        return RideResource::collection($rides);
    }
}
