<?php

declare(strict_types=1);

namespace Tests\Feature\Maps;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El comando es la prueba de concepto del issue #3 y el único punto que hoy
 * llama al proveedor, así que se cubre completo: parseo de argumentos,
 * resolución del proveedor configurado y presentación del resultado.
 */
class EstimateRouteCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('maps.provider', 'google');
        Config::set('maps.google.key', 'test-key');
    }

    public function test_prints_the_distance_and_duration_of_the_route(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [['distanceMeters' => 7421, 'duration' => '842s']],
            ]),
        ]);

        $this->artisan('maps:estimate', [
            'origin' => '10.3910,-75.4794',
            'destination' => '10.4236,-75.5378',
        ])
            ->expectsOutputToContain('7421 m')
            ->expectsOutputToContain('842 s')
            ->assertSuccessful();
    }

    public function test_fails_with_a_readable_message_when_a_coordinate_is_malformed(): void
    {
        Http::fake();

        $this->artisan('maps:estimate', [
            'origin' => 'cartagena',
            'destination' => '10.4236,-75.5378',
        ])
            ->expectsOutputToContain('Coordenada inválida')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fails_with_a_readable_message_when_the_provider_rejects_the_request(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 403),
        ]);

        $this->artisan('maps:estimate', [
            'origin' => '10.3910,-75.4794',
            'destination' => '10.4236,-75.5378',
        ])
            ->expectsOutputToContain('HTTP 403')
            ->assertFailed();
    }
}
