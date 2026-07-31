<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Maps;

use App\DTOs\Coordinates;
use App\Exceptions\RouteEstimationFailed;
use App\Services\Maps\GoogleRoutesEstimator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prueba de concepto del issue #3: confirma que se obtiene distancia y tiempo
 * entre dos coordenadas con la Routes API de Google.
 *
 * El proveedor se simula con `Http::fake()` — la comprobación contra la API
 * real se hace a mano con `php artisan maps:estimate` y una key válida, porque
 * el CI no tiene credenciales ni debería gastar cuota en cada corrida. Lo que
 * sí se verifica acá, y es lo que el CI puede proteger, es el contrato: qué se
 * manda (modo moto, field mask, coordenadas en orden) y cómo se interpreta lo
 * que vuelve.
 */
class GoogleRoutesEstimatorTest extends TestCase
{
    private const ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    private function estimator(): GoogleRoutesEstimator
    {
        return new GoogleRoutesEstimator('test-key', self::ENDPOINT, 5);
    }

    private function origin(): Coordinates
    {
        return new Coordinates(10.3910, -75.4794);
    }

    private function destination(): Coordinates
    {
        return new Coordinates(10.4236, -75.5378);
    }

    public function test_returns_distance_and_duration_from_the_provider_response(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'routes' => [
                    ['distanceMeters' => 7421, 'duration' => '842s'],
                ],
            ]),
        ]);

        $estimate = $this->estimator()->estimate($this->origin(), $this->destination());

        $this->assertSame(7421, $estimate->distanceMeters);
        $this->assertSame(842, $estimate->durationSeconds);
    }

    public function test_sends_both_coordinates_in_two_wheeler_mode_with_a_minimal_field_mask(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'routes' => [
                    ['distanceMeters' => 7421, 'duration' => '842s'],
                ],
            ]),
        ]);

        $this->estimator()->estimate($this->origin(), $this->destination());

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->url() === self::ENDPOINT
                && $request->hasHeader('X-Goog-Api-Key', 'test-key')
                && $request->hasHeader('X-Goog-FieldMask', 'routes.distanceMeters,routes.duration')
                && $body['travelMode'] === 'TWO_WHEELER'
                && $body['origin']['location']['latLng'] === ['latitude' => 10.3910, 'longitude' => -75.4794]
                && $body['destination']['location']['latLng'] === ['latitude' => 10.4236, 'longitude' => -75.5378];
        });
    }

    public function test_rounds_a_fractional_duration_to_whole_seconds(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'routes' => [
                    ['distanceMeters' => 100, 'duration' => '842.6s'],
                ],
            ]),
        ]);

        $estimate = $this->estimator()->estimate($this->origin(), $this->destination());

        $this->assertSame(843, $estimate->durationSeconds);
    }

    public function test_fails_when_the_provider_returns_an_error_status(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['error' => ['message' => 'API key not valid']], 403),
        ]);

        $this->expectException(RouteEstimationFailed::class);
        $this->expectExceptionMessage('respondió con HTTP 403');

        $this->estimator()->estimate($this->origin(), $this->destination());
    }

    public function test_fails_when_there_is_no_route_between_both_points(): void
    {
        // La Routes API responde 200 con un cuerpo vacío cuando no hay ruta.
        Http::fake([self::ENDPOINT => Http::response([])]);

        $this->expectException(RouteEstimationFailed::class);
        $this->expectExceptionMessage('no devolvió ninguna ruta');

        $this->estimator()->estimate($this->origin(), $this->destination());
    }

    public function test_fails_when_the_route_has_no_distance_or_duration(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['routes' => [['distanceMeters' => 7421]]]),
        ]);

        $this->expectException(RouteEstimationFailed::class);
        $this->expectExceptionMessage('distanceMeters y/o duration');

        $this->estimator()->estimate($this->origin(), $this->destination());
    }

    public function test_fails_when_the_duration_format_is_unexpected(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'routes' => [['distanceMeters' => 7421, 'duration' => 842]],
            ]),
        ]);

        $this->expectException(RouteEstimationFailed::class);
        $this->expectExceptionMessage('duration no tiene el formato');

        $this->estimator()->estimate($this->origin(), $this->destination());
    }

    public function test_fails_when_the_provider_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        $this->expectException(RouteEstimationFailed::class);
        $this->expectExceptionMessage('No se pudo contactar');

        $this->estimator()->estimate($this->origin(), $this->destination());
    }
}
