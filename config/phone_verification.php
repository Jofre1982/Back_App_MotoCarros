<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Confirmación del número de celular por SMS
    |--------------------------------------------------------------------------
    |
    | Parámetros del código que se envía a `POST /me/phone/verification`
    | (historia #69). Ver App\Actions\Auth\RequestPhoneVerificationAction.
    |
    */

    // Cantidad de dígitos del código, p. ej. 6 -> códigos entre 000000 y 999999.
    'code_length' => (int) env('PHONE_VERIFICATION_CODE_LENGTH', 6),

    // Minutos que el código sigue siendo válido después de generado.
    'expires_in_minutes' => (int) env('PHONE_VERIFICATION_EXPIRES_IN_MINUTES', 10),

    // Intentos fallidos permitidos antes de exigir pedir un código nuevo.
    'max_attempts' => (int) env('PHONE_VERIFICATION_MAX_ATTEMPTS', 5),

];
