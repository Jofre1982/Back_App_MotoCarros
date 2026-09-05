<?php

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Rides\RideNoLongerAvailableException;
use App\Exceptions\RouteEstimationFailed;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

        // `ApplicationBuilder::withMiddleware()` deja por defecto
        // `redirectGuestsTo(fn () => route('login'))` (pensado para una app
        // web con sesión) *antes* de correr este closure — ver
        // vendor/laravel/framework/.../ApplicationBuilder.php. Este backend
        // es solo API (ver .claude/CLAUDE.md) y no tiene ninguna ruta
        // llamada `login`: sin este override, cualquier request sin token
        // que además no mande `Accept: application/json` (Laravel decide por
        // ahí si "expectsJson", no por el prefijo /api/*) hace que
        // `Authenticate::redirectTo()` intente construir esa URL y explote
        // con un 500 (`RouteNotFoundException`) en vez de responder 401.
        // `fn () => null` dice "nunca redirijas", así que el flujo llega
        // siempre al render JSON (ver `withExceptions()`, `AuthenticationException`
        // más abajo) sin importar qué mande el cliente en `Accept`.
        $middleware->redirectGuestsTo(fn () => null);
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

        // El proveedor de mapas no pudo entregar una ruta utilizable (fuera de
        // cobertura, no contactable, o una respuesta que no se pudo leer). Es
        // 422 y no 502/503: para quien pidió la estimación es indistinguible
        // de una entrada que su formulario no puede resolver, y el mensaje
        // genérico no filtra cuál de los tres motivos fue (ver
        // RouteEstimationFailed).
        $exceptions->render(
            fn (RouteEstimationFailed $e) => new JsonResponse(
                ['message' => 'No fue posible calcular una ruta entre esas coordenadas. Puede que la zona no esté cubierta por el servicio.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            ),
        );

        // El viaje que un conductor intentó aceptar ya no está `requested`
        // (historia #18): otro conductor lo aceptó primero, o cambió de estado
        // por otro motivo. Es 409 y no 422: no es un problema de forma de la
        // entrada ni del estado de la cuenta del conductor —eso ya lo filtró
        // `AcceptRideRequest`— sino de que el recurso cambió entre que se vio
        // disponible y que la petición llegó al servidor.
        $exceptions->render(
            fn (RideNoLongerAvailableException $e) => new JsonResponse(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_CONFLICT,
            ),
        );

        // Los siguientes cuatro traducen mensajes que el propio framework
        // genera en inglés y que ningún Form Request ni Policy del proyecto
        // escribe a mano (historia técnica #73) — a diferencia de los render()
        // de arriba, que traducen excepciones de dominio.

        // Sin token en absoluto (a diferencia de uno vencido o ilegible, que ya
        // cubre TokenExpiredException|TokenInvalidException arriba): el guard
        // `auth:api` resuelve ese caso con la `AuthenticationException` de
        // Laravel, no con una excepción de jwt-auth.
        $exceptions->render(
            fn (AuthenticationException $e) => new JsonResponse(
                ['message' => 'No has iniciado sesión.'],
                JsonResponse::HTTP_UNAUTHORIZED,
            ),
        );

        // Una Policy o un Form Request rechazó la operación por rol o por
        // dueño del recurso (`$this->authorize()` o `Gate::denies()`).
        //
        // Mismo motivo que el `NotFoundHttpException` más abajo:
        // `Handler::prepareException()` convierte `AuthorizationException` en
        // `AccessDeniedHttpException` (cuando no trae un status propio, que es
        // el caso de todas las de este proyecto) *antes* de que corran los
        // `render()` de acá — registrar el render para `AuthorizationException`
        // directamente nunca dispararía.
        $exceptions->render(
            fn (AccessDeniedHttpException $e) => new JsonResponse(
                ['message' => 'No tienes permiso para hacer esto.'],
                JsonResponse::HTTP_FORBIDDEN,
            ),
        );

        $exceptions->render(
            fn (ThrottleRequestsException $e) => new JsonResponse(
                ['message' => 'Se superó el límite de solicitudes. Intenta de nuevo en unos minutos.'],
                JsonResponse::HTTP_TOO_MANY_REQUESTS,
            ),
        );

        // Laravel convierte `ModelNotFoundException` en `NotFoundHttpException`
        // en `Handler::prepareException()`, **antes** de que corran los
        // `render()` registrados acá — así que hay que interceptar
        // `NotFoundHttpException` y no `ModelNotFoundException` directamente
        // (nunca llegaría a verse). La conversión guarda la excepción original
        // como "previous"; eso es lo que distingue un id que no existe (mensaje
        // en inglés del framework, "No query results for model [...] N", que
        // además expone el namespace completo del modelo) de un
        // `NotFoundHttpException` que el propio proyecto lanza a mano con un
        // mensaje ya en español (ej. "No tienes un vehículo registrado" en
        // `ShowVehicleRequest`/`UpdateVehicleRequest`).
        //
        // Los dos casos usan `$e->getMessage()` (genérico para el primero,
        // el propio del proyecto para el segundo) en vez de devolver `null`
        // para "dejarlo pasar": con `APP_DEBUG=true` el renderer por defecto
        // de Laravel para JSON no vuelve a mostrar solo el mensaje, muestra
        // el volcado completo de depuración (clase PHP, archivo, línea y
        // stack trace) — un caso real de fuga de información que esta misma
        // suite detectó.
        $exceptions->render(function (NotFoundHttpException $e) {
            if ($e->getPrevious() instanceof ModelNotFoundException) {
                return new JsonResponse(
                    ['message' => 'No se encontró el recurso solicitado.'],
                    JsonResponse::HTTP_NOT_FOUND,
                );
            }

            return new JsonResponse(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_NOT_FOUND,
            );
        });
    })->create();
