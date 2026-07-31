<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conexión de broadcasting por defecto
    |--------------------------------------------------------------------------
    |
    | Servidor al que se publican los eventos que implementan ShouldBroadcast.
    | En cualquier entorno donde el tiempo real importe esto es "reverb" (ver
    | .claude/STANDARDS.md, "Tiempo real: Laravel Reverb"); "log" y "null"
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
    | Solo están las conexiones que este proyecto usa. Las de Pusher y Ably que
    | trae el framework por defecto se omiten a propósito: el proveedor está
    | decidido y dejarlas acá solo agrega variables de entorno que nadie
    | configura (mismo criterio que la limpieza de scaffolding en
    | .claude/STANDARDS.md). Reverb habla el protocolo de Pusher, así que si
    | alguna vez se migra a Pusher u otro proveedor compatible, es agregar la
    | conexión acá y cambiar BROADCAST_CONNECTION.
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

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
