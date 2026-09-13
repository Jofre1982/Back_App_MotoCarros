<?php

declare(strict_types=1);

namespace App\Actions\Errands;

use App\Enums\ErrandStatus;
use App\Models\Errand;

/**
 * Marca como completado un mandado que el conductor asignado tiene aceptado
 * (historia #92).
 *
 * No hay cobro que disparar ni tarifa que recalcular, a diferencia de
 * `CompleteRideAction`: el precio ya quedó fijado en `agreed_price` al
 * aceptar, y no hay pasarela de pago para mandados (se negocia por fuera).
 * Tampoco hace falta lock: el único que compite por esta fila es el mismo
 * conductor tocando el botón dos veces, mismo criterio que `CompleteRideAction`.
 */
final readonly class CompleteErrandAction
{
    public function handle(Errand $errand): Errand
    {
        $errand->update([
            'status' => ErrandStatus::Completed,
            'completed_at' => now(),
        ]);

        return $errand;
    }
}
