<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\GiftSubscriptionMonthsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GiftSubscriptionMonthsRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\DriverProfile;
use App\Models\User;

class GiftSubscriptionMonthsController extends Controller
{
    public function __invoke(GiftSubscriptionMonthsRequest $request, GiftSubscriptionMonthsAction $gift, User $user): AdminUserResource
    {
        /** @var DriverProfile $profile */
        $profile = $user->driverProfile;
        $gift->handle($profile, $request->months());

        return new AdminUserResource($user->load('driverProfile'));
    }
}
