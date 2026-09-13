<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Errand;
use App\Models\User;

/**
 * Quién puede operar sobre un mandado (historia #92). Mismo criterio que
 * `RidePolicy`: la autorización de negocio vive acá, no en los claims del
 * token ni en el estado del recurso (eso son 422/409, resueltos en el Form
 * Request o la Action).
 */
class ErrandPolicy
{
    /**
     * Pedir un mandado es del rol pasajero, igual que solicitar un viaje.
     */
    public function create(User $user): bool
    {
        return $user->isPassenger();
    }

    /**
     * Ver la lista de mandados disponibles es del rol conductor. Que solo
     * vea algo si además está disponible **para mandados** (historia #92,
     * pool separado del de viajes) no se decide acá: la Policy solo
     * responde a "puede este rol usar este endpoint", y un conductor no
     * disponible sigue teniendo el permiso, solo que `ListErrandsController`
     * le devuelve una lista vacía.
     */
    public function viewAny(User $user): bool
    {
        return $user->isDriver();
    }

    /**
     * Ver el detalle o la foto de un mandado es de quienes participan de él:
     * el pasajero que lo pidió y el conductor asignado, mismo criterio que
     * `RidePolicy::view()`.
     */
    public function view(User $user, Errand $errand): bool
    {
        return $errand->passenger_id === $user->getKey()
            || ($errand->driver_id !== null && $errand->driver_id === $user->getKey());
    }

    /**
     * Aceptar un mandado es del rol conductor, igual que aceptar un viaje: no
     * depende de una fila concreta, cualquier conductor puede intentar
     * aceptar cualquier mandado `requested`. Que ya no esté disponible es 409
     * y lo resuelve `AcceptErrandAction` bajo lock.
     */
    public function accept(User $user): bool
    {
        return $user->isDriver();
    }

    /**
     * Completar un mandado es del conductor **asignado a ese mandado**,
     * mismo criterio que `RidePolicy::complete()`.
     */
    public function complete(User $user, Errand $errand): bool
    {
        return $user->isDriver()
            && $errand->driver_id !== null
            && $errand->driver_id === $user->getKey();
    }
}
