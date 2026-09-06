<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\AuthenticatedUser;
use App\Models\User;
use App\Services\Auth\AccessTokenFactory;
use Tymon\JWTAuth\JWTAuth;

/**
 * Confirma la recuperación de contraseña de una cuenta y la deja autenticada
 * en el mismo paso.
 *
 * Que el código enviado sea correcto, no haya vencido, y no se hayan agotado
 * los intentos lo comprueba `ConfirmPasswordResetRequest` antes de llegar
 * acá, mismo criterio que `ConfirmPhoneVerificationAction` con el suyo: esta
 * Action asume una confirmación válida y no vuelve a validarla.
 *
 * Devuelve un `AuthenticatedUser` (mismo DTO que login y los registros) en
 * vez de solo confirmar el cambio: quien acaba de recuperar el acceso no
 * debería tener que volver a escribir la contraseña que recién eligió para
 * poder entrar.
 */
final class ConfirmPasswordResetAction
{
    public function __construct(
        private readonly JWTAuth $jwt,
        private readonly AccessTokenFactory $tokens,
    ) {}

    public function handle(User $user, #[\SensitiveParameter] string $newPassword): AuthenticatedUser
    {
        $user->password = $newPassword;
        $user->save();

        $user->passwordResetCode?->delete();

        return new AuthenticatedUser(
            user: $user,
            token: $this->tokens->fromJwt($this->jwt->fromUser($user)),
        );
    }
}
