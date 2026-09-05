<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ConfirmPhoneVerificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPhoneVerificationRequest;
use App\Http\Resources\ProfileResource;

/**
 * POST /api/v1/me/phone/verification/confirm
 *
 * Confirma el código de verificación de celular de la cuenta autenticada
 * (historia #69). Que el código sea correcto, no haya vencido, y no se hayan
 * agotado los intentos lo resuelve `ConfirmPhoneVerificationRequest`.
 */
class ConfirmPhoneVerificationController extends Controller
{
    public function __invoke(
        ConfirmPhoneVerificationRequest $request,
        ConfirmPhoneVerificationAction $confirmVerification,
    ): ProfileResource {
        $user = $confirmVerification->handle($request->user());

        return new ProfileResource($user);
    }
}
