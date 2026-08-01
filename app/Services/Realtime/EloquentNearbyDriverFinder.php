<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use App\DTOs\Coordinates;
use App\Models\DriverProfile;

/**
 * Implementación vigente de {@see NearbyDriverFinder} desde la historia #17.
 *
 * No hay extensión geoespacial disponible en SQLite (el motor de desarrollo y
 * de la suite), así que el filtro por radio no se hace en SQL: se traen los
 * conductores disponibles con ubicación conocida y se descarta cada uno en
 * PHP con la fórmula de Haversine. El volumen de conductores simultáneamente
 * disponibles es lo que hace viable esto sin un índice geoespacial; si deja
 * de serlo, el punto de reemplazo es esta clase, no su interfaz.
 */
final readonly class EloquentNearbyDriverFinder implements NearbyDriverFinder
{
    /** Radio medio de la Tierra en metros (esfera IUGG). */
    private const EARTH_RADIUS_METERS = 6_371_000;

    public function __construct(private int $radiusMeters) {}

    /**
     * @return list<int>
     */
    public function near(Coordinates $point): array
    {
        return DriverProfile::query()
            ->where('is_available', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['user_id', 'latitude', 'longitude'])
            ->filter(fn (DriverProfile $driver): bool => $this->distanceMeters(
                $point,
                new Coordinates((float) $driver->latitude, (float) $driver->longitude),
            ) <= $this->radiusMeters)
            ->pluck('user_id')
            ->values()
            ->all();
    }

    private function distanceMeters(Coordinates $a, Coordinates $b): float
    {
        $latA = deg2rad($a->latitude);
        $latB = deg2rad($b->latitude);
        $deltaLat = deg2rad($b->latitude - $a->latitude);
        $deltaLng = deg2rad($b->longitude - $a->longitude);

        $haversine = sin($deltaLat / 2) ** 2
            + cos($latA) * cos($latB) * sin($deltaLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($haversine)));
    }
}
