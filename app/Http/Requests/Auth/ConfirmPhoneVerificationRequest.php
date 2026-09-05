<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

/**
 * Entrada de POST /api/v1/me/phone/verification/confirm — ver openapi.yaml.
 *
 * No define `authorize()`: el recurso *es* la cuenta autenticada, mismo
 * criterio que `UpdateProfileRequest`. No es una operación de un rol en
 * particular.
 */
class ConfirmPhoneVerificationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->validateCode(...),
        ];
    }

    /**
     * Todas las razones por las que un código no se acepta viajan bajo la
     * misma clave `code` —no hay verificación pendiente, venció, se agotaron
     * los intentos, o el valor no coincide—: para el cliente móvil todas
     * piden lo mismo, pedir un código nuevo (`POST /me/phone/verification`).
     *
     * Un intento incorrecto incrementa `attempts` como parte de esta misma
     * validación: es la comprobación que necesita el valor de `code`, así
     * que no tiene sentido repetirla en la Action solo para poder llevar la
     * cuenta ahí.
     */
    private function validateCode(Validator $validator): void
    {
        $verification = $this->pendingVerification();

        if ($verification === null) {
            $validator->errors()->add('code', 'No tienes una verificación pendiente; solicita un código nuevo.');

            return;
        }

        if ($verification->expires_at->isPast()) {
            $validator->errors()->add('code', 'El código venció; solicita uno nuevo.');

            return;
        }

        if ($verification->attempts >= Config::integer('phone_verification.max_attempts')) {
            $validator->errors()->add('code', 'Superaste el número de intentos permitidos; solicita un código nuevo.');

            return;
        }

        if (! Hash::check($this->string('code')->toString(), $verification->code_hash)) {
            $verification->increment('attempts');
            $validator->errors()->add('code', 'El código no es correcto.');
        }
    }

    public function pendingVerification(): ?PhoneVerificationCode
    {
        $user = $this->user();

        return $user instanceof User ? $user->phoneVerificationCode : null;
    }
}
