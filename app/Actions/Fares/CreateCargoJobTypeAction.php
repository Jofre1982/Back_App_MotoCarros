<?php

declare(strict_types=1);

namespace App\Actions\Fares;

use App\Models\CargoJobType;

/**
 * Crea un tipo de acarreo con su precio fijo (historia técnica #86).
 */
final class CreateCargoJobTypeAction
{
    public function handle(string $name, int $price): CargoJobType
    {
        return CargoJobType::create(['name' => $name, 'price' => $price]);
    }
}
