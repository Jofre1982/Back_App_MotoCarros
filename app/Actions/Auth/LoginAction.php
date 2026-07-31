<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\AuthenticatedUser;
use App\DTOs\LoginCredentials;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use App\Services\Auth\AccessTokenFactory;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\JWTAuth;

/**
 * Autentica una cuenta ya registrada y emite su access token.
 *
 * Un solo caso de uso para ambos roles: el rol no participa de la decisión, se
 * lee de la cuenta encontrada y viaja de vuelta para que el cliente sepa qué UI
 * mostrar.
 *
 * Devuelve el mismo `AuthenticatedUser` que los registros, así que la respuesta
 * del login y la del alta tienen exactamente la misma forma.
 */
final class LoginAction
{
    public function __construct(
        private readonly JWTAuth $jwt,
        private readonly AccessTokenFactory $tokens,
    ) {}

    /**
     * @throws InvalidCredentialsException si el email no tiene cuenta o la
     *                                     contraseña no coincide — el mismo
     *                                     fallo para ambos casos.
     */
    public function handle(#[\SensitiveParameter] LoginCredentials $credentials): AuthenticatedUser
    {
        $user = User::firstWhere('email', $credentials->email);

        if ($user === null) {
            // Se gasta un hash contra nada. Sin esto, el email sin cuenta
            // responde sin haber ejecutado bcrypt y el email real responde
            // después de ejecutarlo: la diferencia de tiempo es medible y
            // reconstruye exactamente el oráculo que el mensaje genérico
            // evita. Laravel tampoco lo hace por su cuenta —
            // `EloquentUserProvider::retrieveByCredentials()` devuelve `null`
            // y nadie llega a comparar nada—, así que corresponde acá.
            Hash::make($credentials->password);

            throw new InvalidCredentialsException;
        }

        if (! Hash::check($credentials->password, $user->getAuthPassword())) {
            throw new InvalidCredentialsException;
        }

        return new AuthenticatedUser(
            user: $user,
            token: $this->tokens->fromJwt($this->jwt->fromUser($user)),
        );
    }
}
