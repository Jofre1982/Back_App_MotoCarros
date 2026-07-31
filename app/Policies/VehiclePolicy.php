<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Quién puede operar sobre un vehículo.
 *
 * La autorización de negocio vive acá y no en los claims del token (ver
 * .claude/STANDARDS.md): el `role` del JWT quedó congelado al emitirse, y una
 * cuenta que dejó de ser conductor seguiría registrando motos hasta que su token
 * venciera.
 */
class VehiclePolicy
{
    /**
     * Registrar una moto es una operación del rol conductor.
     *
     * Que la cuenta ya tenga vehículo **no** se decide acá: eso responde 422,
     * porque el conductor sí puede registrar motos y lo que le falta es
     * actualizar la que tiene (historia #13). Un 403 le diría que el permiso le
     * falta, que es otra cosa.
     */
    public function create(User $user): bool
    {
        return $user->isDriver();
    }
}
