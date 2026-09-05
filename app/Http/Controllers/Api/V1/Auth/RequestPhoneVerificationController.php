<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RequestPhoneVerificationAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /api/v1/me/phone/verification
 *
 * Genera un código de verificación de celular y lo envía por SMS a la cuenta
 * autenticada (historia #69).
 *
 * No lleva Form Request propio: no hay nada que validar en el cuerpo, mismo
 * criterio que `LogoutController`. Responde 204 y no un API Resource porque
 * no hay ningún recurso que devolver — el cliente solo necesita saber que el
 * envío se disparó.
 */
class RequestPhoneVerificationController extends Controller
{
    public function __invoke(Request $request, RequestPhoneVerificationAction $requestVerification): Response
    {
        $requestVerification->handle($request->user());

        return response()->noContent();
    }
}
