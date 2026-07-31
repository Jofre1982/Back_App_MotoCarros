<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Cualquier problema con el JWT (ausente, ilegible, firma inválida, o
        // fuera de la ventana de refresh) es un 401 con el mismo formato que el
        // resto de errores de la API. Se resuelve acá y no en cada controller
        // para no repetir manejo de errores ad-hoc (ver .claude/STANDARDS.md).
        $exceptions->render(fn (JWTException $e) => new JsonResponse(
            ['message' => 'Token inválido o expirado. Inicia sesión de nuevo.'],
            JsonResponse::HTTP_UNAUTHORIZED,
        ));
    })->create();
