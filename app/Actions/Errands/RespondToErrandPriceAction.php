<?php

declare(strict_types=1);

namespace App\Actions\Errands;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use App\Services\Push\PushNotifier;
use App\Services\Push\ServiceMessages;
use Illuminate\Support\Facades\Date;

/**
 * El pasajero acepta el precio propuesto (queda confirmado) o lo rechaza
 * (el mandado vuelve a `requested` sin conductor, para que otro lo tome).
 */
final readonly class RespondToErrandPriceAction
{
    public function __construct(private PushNotifier $push) {}

    public function handle(Errand $errand, bool $accept): Errand
    {
        $driverId = $errand->driver_id;
        $errand->update($accept
            ? ['price_confirmed_at' => Date::now()]
            : [
                'status' => ErrandStatus::Requested,
                'driver_id' => null,
                'agreed_price' => null,
                'price_confirmed_at' => null,
            ]);

        $this->push->toUser($driverId, $accept
            ? ServiceMessages::priceAccepted('Ya puedes hacer el pedido.')
            : ServiceMessages::priceRejected());

        return $errand->refresh();
    }
}
