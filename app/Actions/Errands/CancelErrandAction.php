<?php

declare(strict_types=1);

namespace App\Actions\Errands;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use App\Models\User;

/**
 * El pasajero cancela su mandado; el conductor asignado lo suelta y vuelve a
 * la lista para otro (mismo criterio que `CancelRideAction`).
 */
final readonly class CancelErrandAction
{
    public function handle(Errand $errand, User $actor): Errand
    {
        $isAssignedDriver = $errand->driver_id !== null && $errand->driver_id === $actor->getKey();

        $errand->update($isAssignedDriver
            ? [
                'status' => ErrandStatus::Requested,
                'driver_id' => null,
                'agreed_price' => null,
                'price_confirmed_at' => null,
            ]
            : ['status' => ErrandStatus::Cancelled]);

        return $errand->refresh();
    }
}
