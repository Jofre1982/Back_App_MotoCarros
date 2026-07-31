<?php

use App\Exceptions\Auth\InvalidCredentialsException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\UserNotDefinedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sin esto el grupo `api` se queda solo con SubstituteBindings y ningún
        // endpoint tiene límite de tasa. Es el techo general de la API; los
        // endpoints anónimos (auth) llevan encima uno más estricto — ambos
        // limitadores se definen en AppServiceProvider.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Solo los fallos atribuibles al token que mandó el cliente se traducen
        // a 401, con el mismo formato que el resto de errores de la API. Se
        // resuelve acá y no en cada controller para no repetir manejo de
        // errores ad-hoc (ver .claude/STANDARDS.md).
        //
        // Deliberadamente NO se captura la clase base `JWTException`: jwt-auth
        // también la lanza ante errores de configuración del servidor
        // (JWT_SECRET sin generar, algoritmo inexistente, blacklist
        // deshabilitada). Mapearla entera respondería 401 "tu sesión venció" a
        // todos los usuarios de un despliegue mal configurado, de forma
        // indefinida y sin ninguna señal de error — indistinguible, desde el
        // cliente, de un token realmente vencido. Esos casos escalan a 500 a
        // propósito, que es lo que el monitoreo tiene que ver.
        //
        // `TokenBlacklistedException` extiende `TokenInvalidException`, así que
        // queda cubierta. Un request sin token también llega como
        // `TokenInvalidException`, porque el controller normaliza el bearer
        // ausente a `''` y jwt-auth lo rechaza al validar la forma del token;
        // el guard `auth:api`, por su lado, resuelve la ausencia de token con
        // la `AuthenticationException` de Laravel, que ya renderiza 401.
        $exceptions->render(
            fn (TokenExpiredException|TokenInvalidException|UserNotDefinedException $e) => new JsonResponse(
                ['message' => 'Token inválido o expirado. Inicia sesión de nuevo.'],
                JsonResponse::HTTP_UNAUTHORIZED,
            ),
        );

        // Credenciales que no corresponden a ninguna cuenta (login, historia
        // #8). Se traduce acá y no en el controller por el mismo motivo que lo
        // anterior, y además porque el cuerpo tiene que ser palabra por palabra
        // el mismo para los dos motivos posibles —contraseña incorrecta y email
        // sin cuenta—: definirlo en un solo lugar es lo que garantiza que no
        // puedan separarse. `$e->getMessage()` ya es ese mensaje genérico (ver
        // InvalidCredentialsException); la excepción no lleva ningún dato del
        // motivo, así que no hay nada que se pueda filtrar por acá.
        $exceptions->render(
            fn (InvalidCredentialsException $e) => new JsonResponse(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_UNAUTHORIZED,
            ),
        );
    })->create();
