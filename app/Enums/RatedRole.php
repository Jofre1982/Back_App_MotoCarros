<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A quién califica una fila de `ride_ratings`: al conductor (historia #27) o
 * al pasajero (historia #28) de ese mismo viaje.
 *
 * Los valores viajan tal cual a `ride_ratings.rated_role`. Junto con
 * `ride_id`, sostienen el índice único que impide que la misma dirección se
 * registre dos veces para un viaje (ver la migración).
 */
enum RatedRole: string
{
    case Driver = 'driver';
    case Passenger = 'passenger';
}
