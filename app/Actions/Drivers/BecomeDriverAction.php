<?php

declare(strict_types=1);

namespace App\Actions\Drivers;

use App\Models\DriverProfile;
use App\Models\User;

/**
 * Activa el modo conductor en una cuenta existente (historia #99): le crea
 * el perfil de conductor que un conductor recibe al registrarse (ver
 * `RegisterDriverAction`). No toca `users.role`: la cuenta conserva el rol con
 * el que se registró, y desde acá `User::isDriver()` ya la reconoce también
 * como conductor por tener perfil.
 *
 * El perfil nace igual que en el registro: sin disponibilidad, sin ubicación
 * y con la verificación `pending` hasta que el admin apruebe los documentos.
 */
final readonly class BecomeDriverAction
{
    public function handle(User $user, string $licenseNumber): DriverProfile
    {
        $profile = $user->driverProfile()->create([
            'license_number' => $licenseNumber,
        ]);

        $user->setRelation('driverProfile', $profile);

        return $profile;
    }
}
