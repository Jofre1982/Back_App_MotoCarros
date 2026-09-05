<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\VehicleType;

/**
 * Datos con los que un conductor da de alta su moto, ya validados.
 *
 * No lleva dueño, por la misma razón por la que los DTOs de registro no llevan
 * rol: quién es el conductor lo decide quien invoca la Action —el guard, en el
 * caso del endpoint— y no los datos que manda el cliente. `user_id` es fillable
 * en el modelo, así que si el campo existiera acá habría un camino por el que un
 * cliente le registraría una moto a otra persona.
 */
final readonly class VehicleRegistration
{
    public function __construct(
        public string $plate,
        public VehicleType $type,
        public int $year,
    ) {}
}
