<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Autorización sobre las cuentas de otros usuarios (historia #97).
 *
 * Solo el administrador puede listarlas, mismo criterio que `SitePolicy` y
 * `DriverDocumentPolicy`: es staff operando sobre cuentas de terceros, no el
 * dueño de ninguna de ellas.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
