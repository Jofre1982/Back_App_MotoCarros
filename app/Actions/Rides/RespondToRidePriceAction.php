<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Enums\RideStatus;
use App\Events\Realtime\RideStatusChanged;
use App\Models\Ride;
use App\Services\Push\PushNotifier;
use App\Services\Push\ServiceMessages;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * El pasajero acepta el precio propuesto (queda confirmado) o lo rechaza
 * (el viaje vuelve a `requested` sin conductor, para que otro lo tome).
 */
final readonly class RespondToRidePriceAction
{
    public function __construct(private PushNotifier $push) {}

    public function handle(Ride $ride, bool $accept): Ride
    {
        $driverId = $ride->driver_id;
        $ride->update($accept
            ? ['price_confirmed_at' => Date::now()]
            : [
                'status' => RideStatus::Requested,
                'driver_id' => null,
                'driver_vehicle_plate' => null,
                'driver_vehicle_type' => null,
                'agreed_price' => null,
                'price_confirmed_at' => null,
                // Si era para su favorito, ya no espera: queda para todos.
                ...$this->endPreference($ride),
            ]);

        RideStatusChanged::dispatch($ride->id, $ride->status, $ride->driver_id, $ride->agreed_price);

        $this->push->toUser($driverId, $accept
            ? ServiceMessages::priceAccepted('Ve a recoger al pasajero.')
            : ServiceMessages::priceRejected());

        return $ride->refresh();
    }

    /**
     * @return array{preferred_until?: CarbonInterface}
     */
    private function endPreference(Ride $ride): array
    {
        return $ride->preferred_until?->isFuture() === true ? ['preferred_until' => Date::now()] : [];
    }
}
