<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use App\DTOs\PushNotification;
use App\Models\DeviceToken;

/**
 * Contrato con el proveedor de notificaciones push (historia #67).
 *
 * `CreateRideAction` depende de esta interfaz y nunca de una implementación
 * concreta, mismo criterio que `PaymentGateway`: qué proveedor se usa
 * (Firebase Cloud Messaging, APNs directo) no está decidido todavía —no hay
 * cuenta de Firebase configurada—, y el punto de integración tiene que poder
 * cambiar sin tocar `CreateRideAction`.
 */
interface PushNotificationGateway
{
    /**
     * Envía `$notification` al dispositivo de `$token`. No devuelve nada: el
     * éxito es que el método retorne sin lanzar, mismo criterio que
     * `PaymentGateway::charge()`.
     */
    public function send(DeviceToken $token, PushNotification $notification): void;
}
