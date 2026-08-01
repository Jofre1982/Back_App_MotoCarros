<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DriverProfile;
use App\Models\User;

/**
 * Quién puede operar sobre el perfil de conductor.
 *
 * Mismo criterio que `VehiclePolicy` y `RidePolicy`: la autorización de
 * negocio vive acá y no en los claims del token (ver .claude/STANDARDS.md).
 */
class DriverProfilePolicy
{
    /**
     * Marcar disponibilidad y publicar ubicación es del conductor **dueño**
     * de ese perfil (historia #17).
     *
     * `PATCH /me/availability` no lleva id en la ruta —se llega al perfil por
     * la cuenta que manda el token—, así que hoy no existe una request capaz
     * de traer acá el perfil de otro conductor. La comprobación de propiedad
     * se escribe igual, mismo criterio que `VehiclePolicy::update()`: la
     * garantía tiene que vivir en la regla y no en la forma de la ruta de
     * hoy.
     *
     * El rol se comprueba además de la propiedad: nada impide que una fila
     * apunte a una cuenta que dejó de ser conductor.
     */
    public function updateAvailability(User $user, DriverProfile $profile): bool
    {
        return $user->isDriver() && $profile->user_id === $user->getKey();
    }
}
