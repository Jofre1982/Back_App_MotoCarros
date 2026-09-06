<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recuperación de contraseña por SMS
    |--------------------------------------------------------------------------
    |
    | Parámetros del código que se envía a `POST /auth/password/forgot`. Ver
    | App\Actions\Auth\RequestPasswordResetAction. Mismos valores por defecto
    | que `config/phone_verification.php` porque es la misma UX (código de
    | un solo uso por SMS), aunque son flujos y configuraciones separadas.
    |
    */

    // Cantidad de dígitos del código, p. ej. 6 -> códigos entre 000000 y 999999.
    'code_length' => (int) env('PASSWORD_RESET_CODE_LENGTH', 6),

    // Minutos que el código sigue siendo válido después de generado.
    'expires_in_minutes' => (int) env('PASSWORD_RESET_EXPIRES_IN_MINUTES', 10),

    // Intentos fallidos permitidos antes de exigir pedir un código nuevo.
    'max_attempts' => (int) env('PASSWORD_RESET_MAX_ATTEMPTS', 5),

];
