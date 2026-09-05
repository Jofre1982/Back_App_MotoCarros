<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * Contrato con el proveedor de SMS (historia #69).
 *
 * `RequestPhoneVerificationAction` depende de esta interfaz y nunca de una
 * implementación concreta, mismo criterio que `PaymentGateway` y
 * `PushNotificationGateway`: qué proveedor se usa (Twilio u otro) no está
 * decidido todavía, y el punto de integración tiene que poder cambiar sin
 * tocar la Action.
 */
interface SmsGateway
{
    /**
     * Envía `$message` al número `$phone`. No devuelve nada: el éxito es que
     * el método retorne sin lanzar, mismo criterio que
     * `PaymentGateway::charge()`.
     */
    public function send(string $phone, string $message): void;
}
