<?php

declare(strict_types=1);

namespace App\Actions\Drivers;

use App\Models\DriverProfile;

/**
 * Marca al conductor disponible o no para tomar mandados (historia #92).
 *
 * Independiente de `UpdateDriverAvailabilityAction` (viajes de pasajero): un
 * conductor puede tener cualquier combinación de las dos disponibilidades,
 * por eso es una columna y una Action separadas y no un campo más en la
 * misma. A diferencia de esa Action, no lleva ubicación: no hay matching por
 * cercanía para mandados (fuera de alcance de #92, el conductor los busca en
 * `GET /errands`).
 */
final readonly class UpdateDriverErrandAvailabilityAction
{
    public function handle(DriverProfile $profile, bool $isAvailable): DriverProfile
    {
        $profile->update(['is_available_for_errands' => $isAvailable]);

        return $profile;
    }
}
