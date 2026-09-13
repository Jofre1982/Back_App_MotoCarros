<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\PaymentProcessingFailed;
use App\Models\Ride;

/**
 * Contrato con el proveedor de pago que procesa el cobro de un viaje.
 *
 * `ChargeRideAction` depende de esta interfaz y nunca de una implementación
 * concreta, mismo criterio que `RouteEstimator`. **El cobro es siempre en
 * efectivo, decisión confirmada de producto** (no una integración pendiente
 * de decidir): el dinero cambia de manos entre pasajero y conductor fuera
 * del sistema, y esta interfaz solo existe para que `ChargeRideAction`
 * tenga un punto único donde registrar que el viaje quedó cobrado. Si algún
 * día se agrega un medio de pago electrónico, el punto de integración puede
 * cambiar sin tocar `ChargeRideAction` ni `CompleteRideAction`.
 */
interface PaymentGateway
{
    /**
     * Procesa el cobro de `$ride->final_fare`. No devuelve nada: el éxito es
     * que el método retorne sin lanzar.
     *
     * @throws PaymentProcessingFailed si el proveedor rechaza el cobro o no
     *                                 se lo puede contactar.
     */
    public function charge(Ride $ride): void;
}
