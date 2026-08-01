<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida de un viaje.
 *
 * Los valores viajan tal cual a `rides.status` y al campo `status` del schema
 * `Ride` de openapi.yaml, así que renombrar uno rompe la base y el contrato
 * publicado a la vez.
 */
enum RideStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Los estados en los que el viaje todavía ocupa al pasajero y al conductor.
     *
     * Es lo que decide si un pasajero puede solicitar otro viaje. La tabla
     * `rides` repite esta lista en la columna generada que sostiene el índice de
     * "un viaje activo por pasajero": si acá se agrega un estado activo y allá
     * no, la regla se cae en silencio. `RideSchemaTest` recorre estos casos
     * contra la base justamente para que esa divergencia falle en la suite.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Requested, self::Accepted, self::InProgress];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), strict: true);
    }
}
