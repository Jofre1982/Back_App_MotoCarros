<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proveedor de mapas
    |--------------------------------------------------------------------------
    |
    | Proveedor externo que resuelve distancia y tiempo estimado entre dos
    | coordenadas. La decisión vigente y su justificación están en
    | .claude/STANDARDS.md ("Proveedor de mapas/geocoding"). El valor se lee
    | al resolver App\Services\Maps\RouteEstimator en el contenedor, así que
    | cambiar de proveedor no toca a quien lo consume.
    |
    */

    'provider' => env('MAPS_PROVIDER', 'google'),

    'google' => [

        // Sin default a propósito: cada entorno usa su propia API key
        // restringida. La app falla al resolver el estimador si falta, en vez
        // de mandar requests anónimos que el proveedor rechaza.
        'key' => env('GOOGLE_MAPS_API_KEY'),

        // Routes API v2 (computeRoutes). Configurable para poder apuntar a un
        // mock/proxy en entornos de prueba sin tocar código.
        'routes_endpoint' => env(
            'GOOGLE_MAPS_ROUTES_ENDPOINT',
            'https://routes.googleapis.com/directions/v2:computeRoutes',
        ),

        // Segundos de espera antes de darse por vencido. Corto a propósito:
        // esta llamada ocurre mientras el pasajero espera la tarifa estimada
        // en pantalla.
        'timeout' => (int) env('GOOGLE_MAPS_TIMEOUT', 5),

    ],

];
