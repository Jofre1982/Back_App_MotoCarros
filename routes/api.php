<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterDriverController;
use App\Http\Controllers\Api\V1\Auth\RegisterPassengerController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Autorización de los canales privados de Reverb. La llama el SDK del
    // cliente (Echo/pusher-js) por su cuenta al suscribirse, no la app móvil a
    // mano. Se declara acá, y no con `withBroadcasting()` en bootstrap/app.php,
    // para que quede versionada bajo /api/v1 y solo con POST: ese helper la
    // registra con GET y POST fuera del prefijo de la API (ver
    // App\Providers\BroadcastServiceProvider).
    //
    // Lleva `auth:api` como cualquier ruta protegida: acá el token expirado no
    // sirve —a diferencia del refresh— y quién es el usuario es exactamente lo
    // que las reglas de routes/channels.php necesitan resolver.
    Route::middleware('auth:api')
        ->post('broadcasting/auth', [BroadcastController::class, 'authenticate'])
        ->name('broadcasting.auth');

    Route::prefix('auth')->name('auth.')->group(function () {
        // Estos van sin `auth:api` a propósito, así que cualquiera puede
        // golpearlos sin credenciales. Por eso llevan un límite de tasa más
        // estricto que el general de la API (ver AppServiceProvider); para el
        // login es, además, la contención principal contra la fuerza bruta
        // sobre contraseñas.
        Route::middleware('throttle:auth')->group(function () {
            Route::post('login', LoginController::class)->name('login');
            Route::post('refresh', RefreshTokenController::class)->name('refresh');
            Route::post('register/driver', RegisterDriverController::class)->name('register.driver');
            Route::post('register/passenger', RegisterPassengerController::class)->name('register.passenger');
        });

        // El cierre de sesión, en cambio, exige un token vigente, así que se
        // queda con el limitador general de la API (60/min por usuario). Bajo
        // `throttle:auth` compartiría la cuota por IP con los endpoints
        // anónimos, y una IP con muchos usuarios detrás —el NAT de una oficina,
        // una red móvil— se quedaría sin poder cerrar sesión porque otros
        // estuvieron intentando entrar.
        Route::middleware('auth:api')
            ->post('logout', LogoutController::class)
            ->name('logout');
    });
});
