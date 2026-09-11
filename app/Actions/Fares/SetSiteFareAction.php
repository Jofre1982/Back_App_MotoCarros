<?php

declare(strict_types=1);

namespace App\Actions\Fares;

use App\DTOs\SiteFareUpdate;
use App\Models\Site;
use App\Models\SiteFare;

/**
 * Fija (o reemplaza) el precio de pasajero de un sitio para un tipo de
 * vehículo (historia técnica #85).
 *
 * Es un upsert por `(site, vehicle_type)` — el índice único de la tabla es
 * lo mismo que garantiza que nunca haya dos precios de Motocarro para el
 * mismo sitio— y no un alta simple: el admin va a ajustar estos montos con
 * frecuencia (alza de gasolina, demanda), y no tiene sentido que tenga que
 * borrar el precio anterior antes de poder cargar el nuevo.
 */
final class SetSiteFareAction
{
    public function handle(Site $site, SiteFareUpdate $update): SiteFare
    {
        // `SiteFare::query()` y no `$site->fares()`: el `updateOrCreate` de la
        // relación devuelve el `Model` base para PHPStan/Larastan, que no
        // puede seguir el tipo concreto a través del proxy dinámico de la
        // relación. La consulta directa sobre el modelo sí queda tipada como
        // `SiteFare`, sin necesidad de un cast que solo silenciaría el error.
        return SiteFare::query()->updateOrCreate(
            ['site_id' => $site->id, 'vehicle_type' => $update->vehicleType],
            [
                'pricing_unit' => $update->pricingUnit,
                'day_price' => $update->dayPrice,
                'night_price' => $update->nightPrice,
            ],
        );
    }
}
