<?php

use Illuminate\Support\Facades\Route;

// Backend API-only: los endpoints de negocio viven en routes/api.php (/api/v1).
// La unica ruta web es este health check en la raiz: no es un endpoint de
// negocio, sirve para monitoreo/uptime y para orientar a quien entra al dominio
// hacia donde esta la API.
Route::get('/', fn () => response()->json([
    'app' => config('app.name'),
    'status' => 'ok',
    'api' => url('/api/v1'),
]));
