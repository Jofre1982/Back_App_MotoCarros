<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * Marca el celular de la cuenta como verificado (historia #69).
 *
 * Que el código enviado sea correcto, no haya vencido, y no se hayan agotado
 * los intentos lo comprueba `ConfirmPhoneVerificationRequest` antes de llegar
 * acá (422 si no), mismo criterio que `ApproveDriverDocumentAction` con el
 * estado del documento: esta Action asume una confirmación válida y no
 * vuelve a validarla.
 *
 * `phone_verified_at` no es fillable a propósito (ver `User`): se asigna
 * directo y se guarda, mismo criterio que `verification_status` en
 * `DriverProfile`.
 */
final class ConfirmPhoneVerificationAction
{
    public function handle(User $user): User
    {
        $user->phone_verified_at = Date::now();
        $user->save();

        $user->phoneVerificationCode?->delete();

        return $user;
    }
}
