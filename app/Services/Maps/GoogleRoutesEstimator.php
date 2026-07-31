<?php

declare(strict_types=1);

namespace App\Services\Maps;

use App\DTOs\Coordinates;
use App\DTOs\RouteEstimate;
use App\Exceptions\RouteEstimationFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Estimador contra Google Maps Platform — Routes API v2 (`computeRoutes`).
 *
 * Dos detalles del request no son negociables para MotoYa:
 *
 * - `travelMode: TWO_WHEELER` — es el modo de moto de la Routes API. Sin él,
 *   la ruta se calcula como si fuera un auto y las estimaciones quedan largas
 *   en las ciudades donde el moto-taxi tiene sentido.
 * - `X-Goog-FieldMask` — la Routes API es obligatoria en este header y además
 *   el SKU (y por lo tanto el precio por request) depende de los campos que se
 *   pidan. Se piden solo distancia y duración a propósito: pedir el polyline o
 *   los tramos sube de tier sin que hoy los usemos.
 */
final readonly class GoogleRoutesEstimator implements RouteEstimator
{
    private const PROVIDER = 'google';

    private const FIELD_MASK = 'routes.distanceMeters,routes.duration';

    public function __construct(
        private string $apiKey,
        private string $endpoint,
        private int $timeoutSeconds,
    ) {}

    public function estimate(Coordinates $origin, Coordinates $destination): RouteEstimate
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => self::FIELD_MASK,
                ])
                ->post($this->endpoint, [
                    'origin' => $this->waypoint($origin),
                    'destination' => $this->waypoint($destination),
                    'travelMode' => 'TWO_WHEELER',
                    'routingPreference' => 'TRAFFIC_AWARE',
                    'units' => 'METRIC',
                ]);
        } catch (ConnectionException $e) {
            throw RouteEstimationFailed::providerUnreachable(self::PROVIDER, $e->getMessage());
        }

        if ($response->failed()) {
            throw RouteEstimationFailed::providerRejectedRequest(self::PROVIDER, $response->status());
        }

        return $this->toEstimate($response->json('routes.0'));
    }

    /**
     * @return array<string, array<string, array<string, float>>>
     */
    private function waypoint(Coordinates $point): array
    {
        return [
            'location' => [
                'latLng' => [
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                ],
            ],
        ];
    }

    /**
     * La Routes API responde 200 con `{}` cuando no existe ruta entre ambos
     * puntos (p. ej. una coordenada en el mar), así que la ausencia de ruta se
     * detecta acá y no en el código de estado.
     */
    private function toEstimate(mixed $route): RouteEstimate
    {
        if (! is_array($route)) {
            throw RouteEstimationFailed::noRouteAvailable(self::PROVIDER);
        }

        if (! isset($route['distanceMeters'], $route['duration'])) {
            throw RouteEstimationFailed::unreadableResponse(
                self::PROVIDER,
                'la ruta no trae distanceMeters y/o duration.',
            );
        }

        return new RouteEstimate(
            distanceMeters: (int) $route['distanceMeters'],
            durationSeconds: $this->secondsFrom($route['duration']),
        );
    }

    /**
     * `duration` viene como un Duration de protobuf serializado a string
     * (`"842s"`, a veces con fracción), no como un número.
     */
    private function secondsFrom(mixed $duration): int
    {
        if (! is_string($duration) || preg_match('/^(\d+(?:\.\d+)?)s$/', $duration, $matches) !== 1) {
            throw RouteEstimationFailed::unreadableResponse(
                self::PROVIDER,
                'duration no tiene el formato "<segundos>s".',
            );
        }

        return (int) round((float) $matches[1]);
    }
}
