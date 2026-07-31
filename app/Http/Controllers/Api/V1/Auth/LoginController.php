<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;

/**
 * POST /api/v1/auth/login
 *
 * Va sin `auth:api` —quien inicia sesión todavía no tiene token— y por eso
 * queda bajo el limitador `auth`, que acá es además la contención principal
 * contra la fuerza bruta sobre contraseñas.
 *
 * No captura nada: el fallo de credenciales sale de la Action como
 * `InvalidCredentialsException` y se traduce a 401 en bootstrap/app.php, junto
 * al resto de fallos de autenticación. Así el mensaje genérico está definido en
 * un solo lugar y no depende de que cada controller lo repita igual.
 */
class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginAction $login): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($login->handle($request->toCredentials()));
    }
}
