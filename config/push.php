<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notificaciones push
    |--------------------------------------------------------------------------
    |
    | `null` solo deja un registro en el log (desarrollo, tests, o mientras no
    | haya cuenta de Firebase). `fcm` manda las notificaciones por Firebase
    | Cloud Messaging (API HTTP v1) con la cuenta de servicio del proyecto.
    |
    */
    'provider' => env('PUSH_PROVIDER', 'null'),

    'fcm' => [
        // Ruta ABSOLUTA del .json de la cuenta de servicio de Firebase
        // (Configuración del proyecto > Cuentas de servicio). Va fuera de
        // public_html: es una llave privada.
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'send_endpoint' => env(
            'FCM_SEND_ENDPOINT',
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
        ),
        // Corto a propósito: el envío corre después de responder, pero no
        // debe quedarse colgado.
        'timeout' => (int) env('FCM_TIMEOUT', 5),
    ],
];
