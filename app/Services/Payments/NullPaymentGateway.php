<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Ride;

/**
 * Implementación de `PaymentGateway` mientras no hay un proveedor real
 * integrado (historia #25: "el método de pago concreto ... no está decidido
 * todavía").
 *
 * Todo cobro se da por exitoso de inmediato — no hay red de por medio, así
 * que no hay nada que pueda rechazarlo ni quedar inalcanzable. El día que se
 * decida un proveedor real (tarjeta, billetera), se reemplaza el binding en
 * `AppServiceProvider` sin tocar `ChargeRideAction`, mismo criterio que
 * `GoogleRoutesEstimator` reemplazó a cualquier estimador previo.
 */
final class NullPaymentGateway implements PaymentGateway
{
    public function charge(Ride $ride): void {}
}
