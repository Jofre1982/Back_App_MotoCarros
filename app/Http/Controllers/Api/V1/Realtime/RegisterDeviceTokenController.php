<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Realtime;

use App\Actions\Realtime\RegisterDeviceTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Realtime\RegisterDeviceTokenRequest;
use App\Http\Resources\DeviceTokenResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/me/device-token
 *
 * Registra (o actualiza) el token de notificaciones push del dispositivo de
 * la cuenta autenticada (historia #67).
 */
class RegisterDeviceTokenController extends Controller
{
    public function __invoke(
        RegisterDeviceTokenRequest $request,
        RegisterDeviceTokenAction $registerDeviceToken,
    ): JsonResponse {
        $token = $registerDeviceToken->handle($request->user(), $request->toRegistration());

        return (new DeviceTokenResource($token))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
