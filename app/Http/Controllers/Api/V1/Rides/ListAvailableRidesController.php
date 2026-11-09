<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Rides\OpenExpiredPreferredRidesAction;
use App\Enums\RideStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\ListAvailableRidesRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Date;

class ListAvailableRidesController extends Controller
{
    public function __invoke(ListAvailableRidesRequest $request, OpenExpiredPreferredRidesAction $openExpired): AnonymousResourceCollection
    {
        $openExpired->handle();

        if ($request->drivesMotocarga() || ! $request->isAvailableForRides()) {
            return RideResource::collection([]);
        }

        $rides = Ride::query()
            ->where('status', RideStatus::Requested)
            ->whereNull('driver_id')
            ->where('passenger_id', '!=', $request->user()?->getKey())
            // Un viaje pedido a un favorito solo lo ve él, hasta que vence.
            ->where(fn ($query) => $query
                ->whereNull('preferred_driver_id')
                ->orWhere('preferred_driver_id', $request->user()?->getKey())
                ->orWhere('preferred_until', '<=', Date::now()))
            ->with('driver', 'destinationSite')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        return RideResource::collection($rides);
    }
}
