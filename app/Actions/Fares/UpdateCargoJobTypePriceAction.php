<?php

declare(strict_types=1);

namespace App\Actions\Fares;

use App\Models\CargoJobType;

/**
 * Ajusta el precio de un tipo de acarreo ya creado (historia técnica #86).
 * Solo el precio es editable — el nombre identifica el tipo de trabajo y no
 * tiene sentido de negocio para renombrarlo una vez que el admin ya lo usó.
 */
final class UpdateCargoJobTypePriceAction
{
    public function handle(CargoJobType $cargoJobType, int $price): CargoJobType
    {
        $cargoJobType->update(['price' => $price]);

        return $cargoJobType;
    }
}
