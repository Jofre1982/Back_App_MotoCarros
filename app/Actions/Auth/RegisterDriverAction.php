<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\AuthenticatedUser;
use App\DTOs\DriverRegistration;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\AccessTokenFactory;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\JWTAuth;

/**
 * Da de alta un conductor —cuenta más perfil— y lo deja autenticado en el mismo
 * paso, igual que el registro de pasajero.
 *
 * El rol se fija en `UserRole::Driver` y no sale de la entrada: el DTO
 * directamente no tiene campo de rol.
 */
final class RegisterDriverAction
{
    public function __construct(
        private readonly JWTAuth $jwt,
        private readonly AccessTokenFactory $tokens,
    ) {}

    public function handle(DriverRegistration $registration): AuthenticatedUser
    {
        $user = $this->createDriver($registration);

        return new AuthenticatedUser(
            user: $user,
            token: $this->tokens->fromJwt($this->jwt->fromUser($user)),
        );
    }

    /**
     * Crea la cuenta y su perfil como una sola unidad.
     *
     * La transacción no es ceremonia: el Form Request ya validó que la licencia
     * esté libre, pero entre esa consulta y esta escritura cabe otra alta con la
     * misma licencia, y ahí el índice único de `driver_profiles` rechaza el
     * `INSERT`. Sin transacción eso dejaría una cuenta con rol de conductor y
     * sin licencia — un estado que el dominio no contempla, y que además
     * bloquearía el reintento del propio conductor, porque su email y su
     * teléfono ya estarían tomados por esa cuenta a medias.
     */
    private function createDriver(DriverRegistration $registration): User
    {
        return DB::transaction(function () use ($registration): User {
            // La contraseña se guarda hasheada por el cast `hashed` del modelo,
            // no por un Hash::make() acá: así vale para cualquier vía de
            // creación.
            $user = User::create([
                'name' => $registration->name,
                'email' => $registration->email,
                'phone' => $registration->phone,
                'password' => $registration->password,
                'role' => UserRole::Driver,
            ]);

            $user->driverProfile()->create([
                'license_number' => $registration->licenseNumber,
            ]);

            return $user;
        });
    }
}
