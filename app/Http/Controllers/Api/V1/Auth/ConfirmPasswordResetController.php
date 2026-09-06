<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ConfirmPasswordResetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordResetRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use RuntimeException;

/**
 * POST /api/v1/auth/password/reset
 *
 * Confirma la recuperación de contraseña con el código enviado por SMS y deja
 * la cuenta autenticada, mismo criterio que el login y los registros. Que el
 * código sea correcto, no haya vencido, y no se hayan agotado los intentos lo
 * resuelve `ConfirmPasswordResetRequest`.
 */
class ConfirmPasswordResetController extends Controller
{
    public function __invoke(
        ConfirmPasswordResetRequest $request,
        ConfirmPasswordResetAction $confirmReset,
    ): AuthenticatedUserResource {
        $account = $request->account();

        // La validación ya exige una recuperación pendiente para llegar
        // acá, y eso implica una cuenta encontrada por `account()`: esta
        // comprobación es para PHPStan, que no puede inferirlo desde
        // `after()`, no un caso real. Mismo criterio que
        // `ApproveDriverDocumentAction`.
        if (! $account instanceof User) {
            throw new RuntimeException('ConfirmPasswordResetRequest sin cuenta resuelta.');
        }

        return new AuthenticatedUserResource($confirmReset->handle($account, $request->newPassword()));
    }
}
