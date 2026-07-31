<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\Request;

/**
 * GET /api/v1/me
 *
 * Devuelve el perfil de la cuenta que envió el token. Es la primera request de
 * las apps móviles al arrancar: con ella saben quién es el usuario y con qué rol
 * está operando.
 *
 * No hay Action detrás y es a propósito: leer la cuenta que el guard ya resolvió
 * no es un caso de uso de negocio, no decide nada y no cambia nada. Una Action
 * acá sería un pasamanos, y la regla de .claude/STANDARDS.md existe para que la
 * lógica no se esconda en los controllers, no para envolver lo que no la tiene.
 * La #11 (actualizar el perfil) sí la va a necesitar, porque ahí sí se escribe.
 *
 * Tampoco hay Policy: el recurso *es* el usuario autenticado, así que no existe
 * la pregunta "¿puede este usuario ver este perfil?" — no hay forma de pedir el
 * de otra persona. Ver el perfil ajeno (el del conductor asignado a un viaje) es
 * otro endpoint, y ahí la autorización sí tendrá algo que decidir.
 */
class ShowProfileController extends Controller
{
    /**
     * El usuario sale del guard, **no de los claims del token**: el `role` del
     * JWT quedó congelado cuando se emitió y viaja solo para que el cliente
     * elija qué UI mostrar (ver .claude/STANDARDS.md). Este endpoint existe para
     * responder el estado actual de la cuenta, así que leerlo del token
     * respondería el del pasado.
     */
    public function __invoke(Request $request): ProfileResource
    {
        return new ProfileResource($request->user());
    }
}
