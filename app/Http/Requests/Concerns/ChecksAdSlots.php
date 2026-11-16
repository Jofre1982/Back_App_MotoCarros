<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Services\Ads\AdSlots;
use Illuminate\Validation\Validator;

/**
 * 422 bajo `slots` si en esas fechas ya están ocupados todos los cupos.
 */
trait ChecksAdSlots
{
    protected function rejectFullSlots(Validator $validator, string $from, string $to, ?int $exceptId = null): void
    {
        $max = AdSlots::max();
        if (AdSlots::busiestDay($from, $to, $exceptId) >= $max) {
            $validator->errors()->add(
                'slots',
                "Ya hay {$max} anuncios saliendo en esas fechas (son todos los cupos). Cambia las fechas o pausa otro anuncio.",
            );
        }
    }
}
