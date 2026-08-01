<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Quién puede operar sobre un viaje.
 *
 * Como en `VehiclePolicy`, la autorización de negocio vive acá y no en los
 * claims del token: el `role` del JWT quedó congelado al emitirse, y una cuenta
 * que pasó a ser conductor seguiría solicitando viajes como pasajero hasta que
 * su token venciera.
 */
class RidePolicy
{
    /**
     * Solicitar un viaje es una operación del rol pasajero.
     *
     * El conductor no queda afuera del producto por esto: consigue viajes
     * aceptando los que ya existen (historia #18), que es otra operación con su
     * propia regla.
     *
     * Que el pasajero ya tenga un viaje activo **no** se decide acá: eso
     * responde 422, porque el permiso lo tiene y lo que le falta es terminar o
     * cancelar el viaje que ya pidió. Mismo criterio que el segundo vehículo de
     * un conductor en `VehiclePolicy`.
     */
    public function create(User $user): bool
    {
        return $user->isPassenger();
    }
}
