<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sistema operativo del dispositivo que registra un token de notificaciones
 * push (historia #67).
 *
 * Los valores viajan tal cual a `device_tokens.platform`. Hoy nada distingue
 * el envío según la plataforma —`NullPushNotificationGateway` no envía nada
 * de verdad—, pero un proveedor real (FCM/APNs) sí necesita saber cuál es
 * para elegir el canal de entrega.
 */
enum DevicePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';
}
