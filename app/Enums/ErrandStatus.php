<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida de un mandado (historia #92).
 *
 * Más corto que `RideStatus` a propósito: no hay `in_progress` porque no hay
 * nada que marcar "en curso" entre aceptar y completar (el conductor no
 * publica ubicación ni el pasajero sigue un tracker), ni `cancelled` porque
 * la historia no lo pidió.
 */
enum ErrandStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Completed = 'completed';
}
