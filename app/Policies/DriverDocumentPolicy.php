<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DriverDocument;
use App\Models\User;

/**
 * Quién puede subir, consultar y revisar los documentos de verificación de
 * un conductor.
 *
 * A diferencia de `VehiclePolicy`, `upload()`/`viewAny()` no comprueban una
 * fila como dueña: `POST /me/documents` y `GET /me/documents` no llevan id,
 * y el documento (si existe) siempre pertenece al perfil de la cuenta del
 * token. Por eso basta con el rol, mismo criterio que `VehiclePolicy::create()`.
 *
 * `review()`/`reviewAny()` (historia técnica #64) tampoco dependen de la fila:
 * cualquier administrador puede revisar cualquier documento, no hay noción de
 * "documento propio" para ese rol. Se recibe la instancia igual, mismo
 * criterio que el resto de las Policies de este proyecto, por si en el
 * futuro la autorización de revisión llegara a depender de algo del
 * documento (por ejemplo, un administrador limitado a cierta región).
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

    public function reviewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function review(User $user, DriverDocument $document): bool
    {
        return $user->isAdmin();
    }
}
