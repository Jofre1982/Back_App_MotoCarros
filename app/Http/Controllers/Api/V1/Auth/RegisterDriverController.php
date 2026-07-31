<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterDriverAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterDriverRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/register/driver
 *
 * Va sin `auth:api` —quien se registra todavía no tiene cuenta— y por eso
 * queda bajo el limitador `auth`, más estricto que el general de la API.
 */
class RegisterDriverController extends Controller
{
    public function __invoke(
        RegisterDriverRequest $request,
        RegisterDriverAction $registerDriver,
    ): JsonResponse {
        $registered = $registerDriver->handle($request->toRegistration());

        return (new AuthenticatedUserResource($registered))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
