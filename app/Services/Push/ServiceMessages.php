<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\DTOs\PushNotification;
use App\Enums\VehicleType;

/**
 * Los textos de las notificaciones push de viajes, mandados y motocargas.
 * Cortos: Android corta el cuerpo en una o dos líneas.
 */
final class ServiceMessages
{
    public static function newRide(): PushNotification
    {
        return new PushNotification('🛺 Nuevo viaje', 'Un pasajero está esperando. Abre MotoYa para ver la solicitud.');
    }

    public static function newErrand(?VehicleType $type): PushNotification
    {
        return $type === VehicleType::Motocarga
            ? new PushNotification('📦 Nueva motocarga', 'Hay un pedido de motocarga esperando. Abre MotoYa para verlo.')
            : new PushNotification('🛍️ Nuevo mandado', 'Hay un mandado esperando. Abre MotoYa para verlo.');
    }

    public static function priceOffered(string $driverName, ?int $price, string $service): PushNotification
    {
        if ($price === null) {
            return new PushNotification('🛺 ¡Tu conductor va en camino!', "{$driverName} aceptó tu {$service}.");
        }

        return new PushNotification(
            '💬 Te ofrecen un precio',
            "{$driverName} te ofrece ".self::cop($price)." por tu {$service}. Abre MotoYa para aceptarlo o rechazarlo.",
        );
    }

    public static function priceAccepted(string $nextStep): PushNotification
    {
        return new PushNotification('✅ ¡Aceptaron tu precio!', $nextStep);
    }

    public static function priceRejected(): PushNotification
    {
        return new PushNotification('😕 No aceptaron tu precio', 'Abre MotoYa si quieres ofrecer otro.');
    }

    public static function rideStarted(): PushNotification
    {
        return new PushNotification('🚀 ¡Tu viaje comenzó!', 'Buen viaje con MotoYa.');
    }

    public static function rideCompleted(?int $fare): PushNotification
    {
        $valor = $fare === null ? '' : ' Valor: '.self::cop($fare).'.';

        return new PushNotification('🎉 ¡Llegaste!', "Viaje terminado.{$valor} ¡Gracias por viajar con MotoYa!");
    }

    public static function errandCompleted(?int $price, string $service): PushNotification
    {
        $valor = $price === null ? '' : ' Valor: '.self::cop($price).'.';

        return new PushNotification('🎉 ¡Tu pedido está listo!', "Tu {$service} se completó.{$valor}");
    }

    /**
     * @param  string  $what  con su artículo: "el viaje", "el mandado", "la motocarga"
     */
    public static function cancelledByPassenger(string $what): PushNotification
    {
        return new PushNotification('❌ El pasajero canceló', "El pasajero canceló {$what}.");
    }

    public static function errandName(?VehicleType $type): string
    {
        return $type === VehicleType::Motocarga ? 'motocarga' : 'mandado';
    }

    public static function cop(int $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }
}
