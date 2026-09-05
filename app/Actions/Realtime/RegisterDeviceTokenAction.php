<?php

declare(strict_types=1);

namespace App\Actions\Realtime;

use App\DTOs\DeviceTokenRegistration;
use App\Models\DeviceToken;
use App\Models\User;

/**
 * Registra el token de notificaciones push de un dispositivo (historia #67).
 *
 * El token es único sin importar la cuenta (ver la migración): si ya estaba
 * registrado —por esta misma cuenta o por otra, porque el dispositivo cambió
 * de usuario— esto lo actualiza en vez de duplicarlo. `updateOrCreate` filtra
 * por `token`, la única columna que identifica de verdad al dispositivo.
 */
final class RegisterDeviceTokenAction
{
    public function handle(User $user, DeviceTokenRegistration $registration): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            ['token' => $registration->token],
            ['user_id' => $user->getKey(), 'platform' => $registration->platform],
        );
    }
}
