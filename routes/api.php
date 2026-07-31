<?php

use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
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

    // Endpoints de autenticación: van sin `auth:api` a propósito, así que
    // cualquiera puede golpearlos sin credenciales. Por eso el grupo lleva un
    // límite de tasa más estricto que el general de la API — es el patrón que
    // heredan login y registro cuando lleguen (ver AppServiceProvider).
    Route::middleware('throttle:auth')
        ->prefix('auth')
        ->name('auth.')
        ->group(function () {
            Route::post('refresh', RefreshTokenController::class)->name('refresh');
        });
});
