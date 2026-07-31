<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LogoutAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Tymon\JWTAuth\JWT;

/**
 * POST /api/v1/auth/logout
 *
 * Sí lleva `auth:api`, al revés que el refresh: un token vencido ya no sirve
 * para consumir la API, así que no queda nada que cerrar con él y el 401 del
 * guard es la respuesta correcta.
 *
 * Responde 204 y no un API Resource porque no hay ningún recurso que devolver:
 * lo único que el cliente hace con la respuesta es descartar el token que ya
 * tenía (ver .claude/STANDARDS.md, "Envelope de las respuestas").
 */
class LogoutController extends Controller
{
    /**
     * Se cierra **el token que el guard aceptó**, y por eso se lee de la misma
     * instancia con la que lo resolvió (`tymon.jwt`, la que recibe el
     * `JWTGuard`) en vez de volver a leer la cabecera con `bearerToken()`.
     *
     * jwt-auth no busca el token solo en `Authorization`: su cadena de parsers
     * es `AuthHeaders`, `QueryString`, `InputSource`, `RouteParams` y `Cookies`
     * (LaravelServiceProvider), y este repo no la restringe. Con
     * `bearerToken()`, un `POST /api/v1/auth/logout?token=<jwt>` autenticaba
     * bien y aun así respondía 401 con la sesión **sin cerrar** — el peor fallo
     * posible en este endpoint, porque quien perdió el teléfono no puede
     * distinguir ese 401 del de "ya estaba cerrada" y se queda con el token
     * vivo creyendo lo contrario.
     *
     * La convención `(string) $request->bearerToken()` del refresh no aplica
     * acá: ese endpoint va **sin** `auth:api`, así que la cabecera es su única
     * fuente por definición. El logout es el primero que invalida un token
     * *después* de que el guard ya lo parseó.
     */
    public function __invoke(JWT $jwt, LogoutAction $logout): Response
    {
        $logout->handle((string) $jwt->getToken());

        return response()->noContent();
    }
}
