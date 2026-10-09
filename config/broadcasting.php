<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conexión de broadcasting por defecto
    |--------------------------------------------------------------------------
    |
    | Servidor al que se publican los eventos que implementan ShouldBroadcast.
    | En desarrollo local es "reverb"; en producción es "pusher", porque el
    | hosting compartido (cPanel) no puede mantener vivo un servidor de
    | WebSockets (ver .claude/STANDARDS.md, "Tiempo real"). "log" y "null"
    | quedan para desarrollo sin servidor de WebSockets y para la suite de
    | tests, que no debe abrir conexiones.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Conexiones
    |--------------------------------------------------------------------------
    |
    | Solo están las conexiones que este proyecto usa (la de Ably del skeleton
    | se omite a propósito, mismo criterio que la limpieza de scaffolding en
    | .claude/STANDARDS.md). Reverb habla el protocolo de Pusher, así que pasar
    | de una a otra es cambiar BROADCAST_CONNECTION: los eventos, los canales y
    | la ruta de autorización son los mismos.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                // Host/puerto/esquema con los que la aplicación alcanza al
                // servidor de Reverb para publicar. No son necesariamente los
                // mismos que ve la app móvil: en producción el cliente entra
                // por el dominio público con TLS y la API puede publicar
                // contra el host interno.
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Opciones de Guzzle: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                // Pusher enruta por cluster (us2, mt1, sa1...): tiene que ser
                // el mismo de la app en el dashboard y el mismo que configura
                // el SDK de la app móvil.
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true,
            ],
            'client_options' => [
                // Opciones de Guzzle: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
