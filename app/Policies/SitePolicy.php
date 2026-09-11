<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

/**
 * Quién administra el catálogo de sitios y sus precios fijos (historia
 * técnica #85). Solo el rol `admin`, igual que `DriverDocumentPolicy` para
 * la revisión de documentos: no depende de la fila (no hay noción de "sitio
 * propio"), se recibe la instancia igual por si en el futuro un admin queda
 * limitado a cierta región.
 */
class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Site $site): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->isAdmin();
    }
}
