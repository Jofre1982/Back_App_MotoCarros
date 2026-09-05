<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use App\DTOs\PushNotification;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;

/**
 * Implementación de `PushNotificationGateway` mientras no hay un proveedor
 * real integrado (historia #67: no hay cuenta de Firebase/APNs configurada
 * todavía, decisión de negocio explícita).
 *
 * En vez de enviar de verdad, deja un registro en el log: es lo que permite
 * conectar el resto del flujo (registrar el token, avisar a los conductores
 * cercanos) y verificarlo en desarrollo y en tests sin depender de una red ni
 * de credenciales de un proveedor externo. El día que se decida un proveedor
 * real, se reemplaza el binding en `AppServiceProvider` sin tocar
 * `CreateRideAction`, mismo criterio que `NullPaymentGateway`.
 */
final class NullPushNotificationGateway implements PushNotificationGateway
{
    public function send(DeviceToken $token, PushNotification $notification): void
    {
        Log::info('Notificación push (sin proveedor real configurado)', [
            'device_token_id' => $token->id,
            'platform' => $token->platform->value,
            'title' => $notification->title,
            'body' => $notification->body,
        ]);
    }
}
