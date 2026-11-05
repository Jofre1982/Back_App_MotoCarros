<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\DriverProfile;
use Illuminate\Support\Facades\Date;

/**
 * Regala meses de acceso a un conductor: se suman desde lo que todavía le
 * quede, o desde hoy si ya venció o nunca tuvo.
 */
final readonly class GiftSubscriptionMonthsAction
{
    public function handle(DriverProfile $profile, int $months): DriverProfile
    {
        $now = Date::now();
        $current = $profile->subscription_ends_at;
        $from = $current !== null && $current->greaterThan($now) ? $current : $now;

        $profile->update(['subscription_ends_at' => $from->copy()->addMonthsNoOverflow($months)]);

        return $profile;
    }
}
