<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RequestPasswordResetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use Illuminate\Http\Response;

/**
 * POST /api/v1/auth/password/forgot
 *
 * Genera un código de recuperación de contraseña y lo envía por SMS al
 * celular dado, si tiene cuenta (recuperación de contraseña).
 *
 * Responde 204 sin importar si el celular tiene cuenta o no —igual que
 * `RequestPasswordResetAction` nunca informa el resultado—, para que este
 * endpoint no sea un oráculo de qué números están registrados en MotoYa.
 */
class RequestPasswordResetController extends Controller
{
    public function __invoke(RequestPasswordResetRequest $request, RequestPasswordResetAction $requestReset): Response
    {
        $requestReset->handle($request->phone());

        return response()->noContent();
    }
}
