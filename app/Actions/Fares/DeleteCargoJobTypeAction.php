<?php

declare(strict_types=1);

namespace App\Actions\Fares;

use App\Models\CargoJobType;

/**
 * Borra un tipo de acarreo del catálogo (historia técnica #86). Nada lo usa
 * todavía —eso es #87—, así que no hay ningún uso que impedir.
 */
final class DeleteCargoJobTypeAction
{
    public function handle(CargoJobType $cargoJobType): void
    {
        $cargoJobType->delete();
    }
}
