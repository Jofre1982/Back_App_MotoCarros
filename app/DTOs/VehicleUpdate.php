<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Datos a corregir en la moto ya registrada de un conductor, ya validados.
 *
 * Es un PATCH parcial: un campo en `null` significa "no vino en la request", no
 * "bórralo" — ninguna de las tres columnas es nullable en `vehicles`.
 *
 * No lleva dueño, por la misma razón que `VehicleRegistration`: a quién
 * pertenece la moto lo decide quien invoca la Action —el guard, en el caso del
 * endpoint— y no los datos que manda el cliente. `user_id` es fillable en el
 * modelo, así que si el campo existiera acá habría un camino por el que un
 * conductor le regalaría su moto a otra cuenta.
 */
final readonly class VehicleUpdate
{
    public function __construct(
        public ?string $plate = null,
        public ?string $model = null,
        public ?int $year = null,
    ) {}
}
