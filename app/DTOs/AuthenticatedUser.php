<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;

/**
 * Una cuenta junto al access token con el que quedó autenticada.
 *
 * Es lo que devuelven los casos de uso que dejan al usuario adentro en un solo
 * paso: hoy el registro, y el login cuando llegue (historia #8). Existe para
 * que la Action no tenga que devolver una tupla suelta y para que el API
 * Resource reciba las dos piezas ya emparejadas.
 */
final readonly class AuthenticatedUser
{
    public function __construct(
        public User $user,
        public AuthToken $token,
    ) {}
}
