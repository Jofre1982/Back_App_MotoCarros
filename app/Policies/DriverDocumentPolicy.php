<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Quién puede subir y consultar los documentos de verificación de un
 * conductor.
 *
 * A diferencia de `VehiclePolicy`, no hay una fila que comprobar como dueña:
 * `POST /me/documents` y `GET /me/documents` no llevan id, y el documento (si
 * existe) siempre pertenece al perfil de la cuenta del token. Por eso basta
 * con el rol, mismo criterio que `VehiclePolicy::create()`.
 */
class DriverDocumentPolicy
{
    public function upload(User $user): bool
    {
        return $user->isDriver();
    }

    public function viewAny(User $user): bool
    {
        return $user->isDriver();
    }
}
