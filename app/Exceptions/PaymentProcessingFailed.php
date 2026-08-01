<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * El proveedor de pago no pudo procesar el cobro de un viaje.
 *
 * A diferencia de `RouteEstimationFailed`, quien atrapa esta excepción
 * (`ChargeRideAction`) no la vuelve a lanzar: la traduce a un `Payment` en
 * estado `failed` para que un cobro rechazado no le impida al conductor
 * cerrar el viaje (historia #25).
 */
final class PaymentProcessingFailed extends RuntimeException
{
    public static function rejectedByProvider(string $provider, string $reason): self
    {
        return new self("El proveedor de pago '{$provider}' rechazó el cobro: {$reason}");
    }

    public static function providerUnreachable(string $provider, string $reason): self
    {
        return new self("No se pudo contactar al proveedor de pago '{$provider}': {$reason}");
    }
}
