<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

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

    /**
     * Corregir los datos de una moto es del conductor **dueño** de esa moto.
     *
     * `PATCH /me/vehicle` no lleva id en la ruta —se llega al vehículo por la
     * cuenta que manda el token—, así que hoy no existe una request capaz de
     * traer acá la moto de otra persona. La comprobación de propiedad se
     * escribe igual: la garantía tiene que vivir en la regla y no en la forma
     * de la ruta de hoy, para que siga en pie si el vehículo llega a
     * direccionarse de otra manera. Se verifica en `VehiclePolicyTest`, que es
     * el único lugar desde donde ese caso es alcanzable.
     *
     * El rol se comprueba además de la propiedad: nada impide que una fila
     * apunte a una cuenta que dejó de ser conductor, y ahí la propiedad sola
     * autorizaría a alguien que ya no opera vehículos.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->isDriver() && $vehicle->user_id === $user->getKey();
    }

    /**
     * Consultar los datos de una moto es del conductor **dueño** de esa moto.
     *
     * Misma regla que `update()` y mismo motivo: `GET /me/vehicle` tampoco
     * lleva id en la ruta, así que hoy no existe una request capaz de traer
     * acá el vehículo de otra persona, pero la garantía se escribe igual en
     * la Policy y no en la forma de la ruta de hoy.
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->isDriver() && $vehicle->user_id === $user->getKey();
    }
}
