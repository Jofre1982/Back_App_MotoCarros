<?php

declare(strict_types=1);

namespace App\Actions\Fares;

use App\Models\SiteFare;
use Illuminate\Support\Carbon;

/**
 * Resuelve el monto que corresponde cobrar de un `SiteFare` a la hora `$at`
 * (historia técnica #85).
 *
 * El recargo nocturno (de 10pm a 5am, confirmado con el dueño del producto)
 * es un dato por sitio (`night_price`), no una regla de código específica
 * para "casco urbano": un sitio sin `night_price` cobra siempre el precio de
 * día, sin importar la hora.
 */
final readonly class ResolveSiteFarePriceAction
{
    public function handle(SiteFare $fare, Carbon $at): int
    {
        if ($fare->night_price !== null && $this->isNightTime($at)) {
            return $fare->night_price;
        }

        return $fare->day_price;
    }

    /**
     * La ventana cruza medianoche (22:00 → 05:00 del día siguiente), así que
     * no alcanza una sola comparación de `hour`: son dos tramos del mismo
     * reloj de 24h.
     */
    private function isNightTime(Carbon $at): bool
    {
        return $at->hour >= 22 || $at->hour < 5;
    }
}
