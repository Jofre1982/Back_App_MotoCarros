<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Ride;

/**
 * Implementación de `PaymentGateway` para el cobro en efectivo (historia
 * #25). **No es un placeholder a la espera de un proveedor real**: el pago
 * en efectivo es la decisión confirmada de producto, no un "todavía no" —
 * el dinero cambia de manos entre pasajero y conductor fuera del sistema, y
 * no hay nada que esta implementación tenga que integrar.
 *
 * Todo cobro se da por exitoso de inmediato — no hay red de por medio, así
 * que no hay nada que pueda rechazarlo ni quedar inalcanzable. Si algún día
 * se agrega un medio de pago electrónico, se reemplaza el binding en
 * `AppServiceProvider` sin tocar `ChargeRideAction`, mismo criterio que
 * `GoogleRoutesEstimator` reemplazó a cualquier estimador previo.
 */
final class NullPaymentGateway implements PaymentGateway
{
    public function charge(Ride $ride): void {}
}
