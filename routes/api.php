<?php

use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
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
