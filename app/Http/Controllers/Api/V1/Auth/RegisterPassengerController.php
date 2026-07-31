<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterPassengerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterPassengerRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/register/passenger
 *
 * Va sin `auth:api` —quien se registra todavía no tiene cuenta— y por eso
 * queda bajo el limitador `auth`, más estricto que el general de la API.
 */
class RegisterPassengerController extends Controller
{
    public function __invoke(
        RegisterPassengerRequest $request,
        RegisterPassengerAction $registerPassenger,
    ): JsonResponse {
        $registered = $registerPassenger->handle($request->toRegistration());

        return (new AuthenticatedUserResource($registered))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
