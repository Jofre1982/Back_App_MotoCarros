<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Actions\Auth\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;

/**
 * PATCH /api/v1/me
 *
 * A diferencia de `ShowProfileController`, acá sí hay una Action detrás: este
 * endpoint escribe, y `.claude/STANDARDS.md` pide que la escritura viva en una
 * Action, no en el controller, para que sirva igual desde un job o un comando.
 *
 * Tampoco hay Policy: el recurso es la cuenta autenticada y no un id de la
 * ruta, así que no existe la pregunta "¿puede este usuario editar este
 * perfil?" — no hay forma de pedir el de otra persona.
 */
class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request, UpdateProfileAction $action): ProfileResource
    {
        $usuario = $action->handle($request->user(), $request->toUpdate());

        return new ProfileResource($usuario);
    }
}
