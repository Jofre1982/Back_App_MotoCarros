<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
