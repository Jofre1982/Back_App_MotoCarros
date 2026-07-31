<?php

declare(strict_types=1);

namespace Tests\Feature\Maps;

use App\Services\Maps\GoogleRoutesEstimator;
use App\Services\Maps\RouteEstimator;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * El contenedor es el único lugar que sabe qué proveedor está configurado; el
 * resto del sistema pide la interfaz. Estos tests cubren esa resolución.
 */
class RouteEstimatorBindingTest extends TestCase
{
    public function test_resolves_the_google_implementation_when_it_is_the_configured_provider(): void
    {
        Config::set('maps.provider', 'google');
        Config::set('maps.google.key', 'test-key');

        $this->assertInstanceOf(GoogleRoutesEstimator::class, $this->app->make(RouteEstimator::class));
    }

    public function test_fails_loudly_when_the_configured_provider_is_unknown(): void
    {
        Config::set('maps.provider', 'waze');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proveedor de mapas no soportado');

        $this->app->make(RouteEstimator::class);
    }

    public function test_fails_loudly_when_the_api_key_is_missing(): void
    {
        Config::set('maps.provider', 'google');
        Config::set('maps.google.key', null);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(RouteEstimator::class);
    }
}
