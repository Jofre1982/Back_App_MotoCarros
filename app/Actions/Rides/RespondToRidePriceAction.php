<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Enums\RideStatus;
use App\Events\Realtime\RideStatusChanged;
use App\Models\Ride;
use Illuminate\Support\Facades\Date;

/**
 * El pasajero acepta el precio propuesto (queda confirmado) o lo rechaza
 * (el viaje vuelve a `requested` sin conductor, para que otro lo tome).
 */
final readonly class RespondToRidePriceAction
{
    public function handle(Ride $ride, bool $accept): Ride
    {
        $ride->update($accept
            ? ['price_confirmed_at' => Date::now()]
            : [
                'status' => RideStatus::Requested,
                'driver_id' => null,
                'agreed_price' => null,
                'price_confirmed_at' => null,
            ]);

        RideStatusChanged::dispatch($ride->id, $ride->status, $ride->driver_id, $ride->agreed_price);

        return $ride->refresh();
    }
}
