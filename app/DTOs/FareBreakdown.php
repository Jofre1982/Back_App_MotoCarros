<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * El resultado del motor de tarifa: el monto a cobrar y de dónde sale.
 *
 * El desglose no es decorativo. La misma fórmula alimenta la tarifa estimada
 * que ve el pasajero antes de pedir el viaje y el cobro al terminarlo; cuando
 * ambos números no coinciden —porque el trayecto real no fue el estimado, o
 * porque hubo espera— hay que poder explicar la diferencia sin recalcular nada
 * a mano.
 *
 * Todos los montos son enteros en la unidad mínima de `$currency`; ver la nota
 * sobre dinero en config/fares.php.
 */
final readonly class FareBreakdown
{
    public function __construct(
        public string $currency,
        public int $base,
        public int $distance,
        public int $time,
        public int $waiting,
        /** Suma de los conceptos anteriores, antes de piso y redondeo. */
        public int $subtotal,
        /** Lo que se cobra: el subtotal con el mínimo y el redondeo ya aplicados. */
        public int $total,
        /** `true` cuando el subtotal no llegaba al mínimo y el cobro lo determinó ese piso. */
        public bool $minimumApplied,
    ) {}
}
