<?php

declare(strict_types=1);

namespace App\Actions\Errands;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use Illuminate\Support\Facades\Date;

/**
 * El pasajero acepta el precio propuesto (queda confirmado) o lo rechaza
 * (el mandado vuelve a `requested` sin conductor, para que otro lo tome).
 */
final readonly class RespondToErrandPriceAction
{
    public function handle(Errand $errand, bool $accept): Errand
    {
        $errand->update($accept
            ? ['price_confirmed_at' => Date::now()]
            : [
                'status' => ErrandStatus::Requested,
                'driver_id' => null,
                'agreed_price' => null,
                'price_confirmed_at' => null,
            ]);

        return $errand->refresh();
    }
}
