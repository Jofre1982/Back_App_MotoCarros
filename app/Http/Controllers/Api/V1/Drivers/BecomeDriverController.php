<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Drivers;

use App\Actions\Drivers\BecomeDriverAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drivers\BecomeDriverRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/me/driver-profile — historia #99.
 */
class BecomeDriverController extends Controller
{
    public function __invoke(BecomeDriverRequest $request, BecomeDriverAction $becomeDriver): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $becomeDriver->handle($user, $request->licenseNumber());

        return (new ProfileResource($user))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
