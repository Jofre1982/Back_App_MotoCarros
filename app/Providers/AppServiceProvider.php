<?php

namespace App\Providers;

use App\Services\Maps\GoogleRoutesEstimator;
use App\Services\Maps\RouteEstimator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRouteEstimator();
    }

    /**
     * Resuelve el proveedor de mapas configurado (ver config/maps.php y la
     * decisión en .claude/STANDARDS.md).
     *
     * La resolución es diferida (`singleton` con closure): la API key solo se
     * exige cuando alguien pide de verdad una estimación, así que el resto de
     * la aplicación —y la suite de tests que no toca mapas— arranca sin
     * necesidad de credenciales del proveedor.
     */
    private function registerRouteEstimator(): void
    {
        $this->app->singleton(RouteEstimator::class, static function (): RouteEstimator {
            $provider = Config::string('maps.provider');

            return match ($provider) {
                'google' => new GoogleRoutesEstimator(
                    apiKey: Config::string('maps.google.key'),
                    endpoint: Config::string('maps.google.routes_endpoint'),
                    timeoutSeconds: Config::integer('maps.google.timeout'),
                ),
                default => throw new InvalidArgumentException(
                    "Proveedor de mapas no soportado: '{$provider}' (revisa MAPS_PROVIDER)."
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Límites de tasa de la API.
     *
     * `api` es el techo general del grupo (ver bootstrap/app.php): por usuario
     * cuando el request viene autenticado, y por IP cuando no.
     *
     * `auth` es más estricto y cubre los endpoints que, por diseño, se consumen
     * sin `auth:api` — hoy solo el refresh, mañana login y registro. Son los
     * que se pueden golpear por fuerza bruta sin credenciales, y en el caso del
     * refresh cada acierto escribe además una entrada de blacklist en el cache.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $porUsuario = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(60)->by((string) ($porUsuario ?? $request->ip()));
        });

        RateLimiter::for(
            'auth',
            fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()),
        );
    }
}
