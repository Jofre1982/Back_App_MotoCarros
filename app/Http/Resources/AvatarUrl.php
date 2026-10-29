<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;

/**
 * URL de la foto de perfil (`GET /users/{id}/avatar`), o `null`.
 *
 * `own()` es para la propia cuenta o un admin: la ven aunque esté pendiente
 * o rechazada. `forOthers()` es lo que ve la otra persona del servicio: solo
 * una foto aprobada (la de un conductor la revisa un admin primero).
 */
final class AvatarUrl
{
    public const string APPROVED = 'approved';

    public const string PENDING = 'pending';

    public const string REJECTED = 'rejected';

    public static function own(User $user): ?string
    {
        return $user->avatar_path === null ? null : route('users.avatar', $user);
    }

    public static function forOthers(User $user): ?string
    {
        return $user->avatar_status === self::APPROVED ? self::own($user) : null;
    }
}
