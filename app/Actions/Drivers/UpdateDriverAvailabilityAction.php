<?php

declare(strict_types=1);

namespace App\Actions\Drivers;

use App\DTOs\DriverAvailabilityUpdate;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\Date;

/**
 * Marca a un conductor disponible o no disponible, y opcionalmente
 * actualiza su última posición conocida (historia #17).
 *
 * Es lo que decide si un conductor entra en la búsqueda de
 * `NearbyDriverFinder` cuando se crea un viaje nuevo: sin esto, `driver.{id}`
 * seguiría siendo un canal autorizado que nunca recibe nada.
 */
final readonly class UpdateDriverAvailabilityAction
{
    public function handle(DriverProfile $profile, DriverAvailabilityUpdate $update): DriverProfile
    {
        $datos = ['is_available' => $update->isAvailable];

        if ($update->location !== null) {
            $datos['latitude'] = $update->location->latitude;
            $datos['longitude'] = $update->location->longitude;
            $datos['location_updated_at'] = Date::now();
        }

        $profile->update($datos);

        return $profile;
    }
}
