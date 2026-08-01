<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\PaymentProcessingFailed;
use App\Models\Ride;

/**
 * Contrato con el proveedor de pago que procesa el cobro de un viaje.
 *
 * `ChargeRideAction` depende de esta interfaz y nunca de una implementación
 * concreta, mismo criterio que `RouteEstimator`: qué proveedor de pago se usa
 * (efectivo conciliado aparte, tarjeta, billetera) no está decidido todavía
 * (ver "Fuera de alcance" de la historia #25), y el punto de integración
 * tiene que poder cambiar sin tocar `ChargeRideAction` ni `CompleteRideAction`.
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
