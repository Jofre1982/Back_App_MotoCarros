<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RefreshTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthTokenResource;
use Illuminate\Http\Request;

/**
 * POST /api/v1/auth/refresh
 *
 * No lleva `auth:api`: el sentido del endpoint es aceptar un token ya expirado,
 * y ese middleware lo rechazaría antes de llegar hasta aquí. La validación del
 * token (firma y ventana de refresh) la hace jwt-auth dentro de la Action.
 */
class RefreshTokenController extends Controller
{
    public function __invoke(Request $request, RefreshTokenAction $refreshToken): AuthTokenResource
    {
        return new AuthTokenResource(
            $refreshToken->handle((string) $request->bearerToken())
        );
    }
}
