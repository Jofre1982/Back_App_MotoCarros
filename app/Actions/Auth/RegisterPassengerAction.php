<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\AuthenticatedUser;
use App\DTOs\PassengerRegistration;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\AccessTokenFactory;
use Tymon\JWTAuth\JWTAuth;

/**
 * Da de alta un pasajero y lo deja autenticado en el mismo paso.
 *
 * Emitir el token acá, y no obligar a un login inmediatamente después, es lo
 * que evita que la app móvil tenga que mandar la contraseña dos veces seguidas
 * por la red para completar un alta.
 *
 * El rol se fija en `UserRole::Passenger` y no sale de la entrada: registrarse
 * como conductor es otro caso de uso (historia #7), con sus propios requisitos
 * de perfil.
 */
final class RegisterPassengerAction
{
    public function __construct(
        private readonly JWTAuth $jwt,
        private readonly AccessTokenFactory $tokens,
    ) {}

    public function handle(PassengerRegistration $registration): AuthenticatedUser
    {
        // La contraseña se guarda hasheada por el cast `hashed` del modelo, no
        // por un Hash::make() acá: así vale para cualquier vía de creación.
        $user = User::create([
            'name' => $registration->name,
            'email' => $registration->email,
            'phone' => $registration->phone,
            'password' => $registration->password,
            'role' => UserRole::Passenger,
        ]);

        return new AuthenticatedUser(
            user: $user,
            token: $this->tokens->fromJwt($this->jwt->fromUser($user)),
        );
    }
}
