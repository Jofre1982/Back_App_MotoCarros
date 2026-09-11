<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cómo se aplica el precio fijo de un sitio (historia técnica #85): algunos
 * destinos cobran por cada pasajero (`PerPerson`), otros un monto único sin
 * importar cuántos vayan (`PerTrip`) — ej. un viaje a una comunidad cuesta lo
 * mismo con uno o con los tres cupos ocupados.
 */
enum PricingUnit: string
{
    case PerPerson = 'per_person';
    case PerTrip = 'per_trip';
}
