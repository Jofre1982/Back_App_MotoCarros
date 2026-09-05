<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Passenger = 'passenger';
    case Driver = 'driver';

    /**
     * Staff que revisa documentos de verificación de conductores (historia
     * técnica #64). No tiene endpoint de registro propio: una cuenta admin se
     * crea manualmente (seeder o `tinker`), nunca por un flujo público como
     * `register/driver` o `register/passenger`.
     */
    case Admin = 'admin';
}
