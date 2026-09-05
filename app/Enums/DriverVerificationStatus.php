<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de verificación general del conductor, calculado a partir de sus
 * `DriverDocument` (ver `DocumentType::required()`).
 *
 * Un conductor recién registrado empieza en `Pending` (default de la
 * migración de `driver_profiles`). Que solo un conductor `Verified` pueda
 * marcarse disponible es una regla de negocio pendiente de conectar en
 * `UpdateDriverAvailabilityAction`, no algo que este enum imponga por sí solo.
 */
enum DriverVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
