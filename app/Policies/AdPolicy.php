<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ad;
use App\Models\User;

/**
 * Los anuncios los administra solo el rol `admin`. Verlos (y contar que se
 * vieron o se tocaron) lo puede cualquier cuenta: la app solo se los muestra
 * al pasajero.
 */
class AdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Ad $ad): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Ad $ad): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Ad $ad): bool
    {
        return $user->isAdmin();
    }
}
