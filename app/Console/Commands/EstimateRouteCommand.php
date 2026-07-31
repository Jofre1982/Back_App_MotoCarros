<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTOs\Coordinates;
use App\Exceptions\RouteEstimationFailed;
use App\Services\Maps\RouteEstimator;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Prueba de concepto del proveedor de mapas (issue #3).
 *
 * Es el único punto del sistema que hoy golpea al proveedor de verdad: sirve
 * para confirmar, con una API key real, que se obtiene distancia y tiempo
 * entre dos coordenadas antes de que el motor de tarifa (issue #4) dependa de
 * ello. Vive en consola y no en la API a propósito — la integración productiva
 * queda fuera del alcance de este issue.
 *
 *   php artisan maps:estimate "10.3910,-75.4794" "10.4236,-75.5378"
 */
final class EstimateRouteCommand extends Command
{
    protected $signature = 'maps:estimate
                            {origin : Coordenada de origen, formato "lat,lng"}
                            {destination : Coordenada de destino, formato "lat,lng"}';

    protected $description = 'Consulta al proveedor de mapas configurado la distancia y el tiempo estimado entre dos coordenadas.';

    public function handle(RouteEstimator $estimator): int
    {
        try {
            $estimate = $estimator->estimate(
                Coordinates::fromString((string) $this->argument('origin')),
                Coordinates::fromString((string) $this->argument('destination')),
            );
        } catch (InvalidArgumentException|RouteEstimationFailed $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Proveedor', (string) config('maps.provider'));
        $this->components->twoColumnDetail('Distancia', "{$estimate->distanceMeters} m");
        $this->components->twoColumnDetail('Duración', "{$estimate->durationSeconds} s");

        return self::SUCCESS;
    }
}
