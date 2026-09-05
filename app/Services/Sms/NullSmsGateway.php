<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Implementación de `SmsGateway` mientras no hay un proveedor real integrado
 * (historia #69: no hay cuenta de Twilio u otro proveedor configurada
 * todavía, decisión de negocio explícita).
 *
 * En vez de enviar de verdad, deja un registro en el log: es lo que permite
 * verificar el resto del flujo (generar el código, confirmarlo) en
 * desarrollo y en tests sin depender de una red ni de credenciales de un
 * proveedor externo. El día que se decida un proveedor real, se reemplaza el
 * binding en `AppServiceProvider` sin tocar
 * `RequestPhoneVerificationAction`, mismo criterio que `NullPaymentGateway`.
 */
final class NullSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS (sin proveedor real configurado)', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
