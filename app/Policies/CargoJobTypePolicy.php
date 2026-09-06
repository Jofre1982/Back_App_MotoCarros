<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CargoJobType;
use App\Models\User;

/**
 * Quién administra el catálogo de tipos de acarreo (historia técnica #86).
 * Solo el rol `admin`, mismo criterio que `SitePolicy`.
 */
class CargoJobTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CargoJobType $cargoJobType): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CargoJobType $cargoJobType): bool
    {
        return $user->isAdmin();
    }
}
