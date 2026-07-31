<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LogoutAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    public function __invoke(Request $request, LogoutAction $logout): Response
    {
        $logout->handle((string) $request->bearerToken());

        return response()->noContent();
    }
}
