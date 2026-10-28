<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesAccountInput;
use App\Http\Requests\Concerns\ResolvesAccountByEmailOrPhone;
use App\Models\PasswordResetCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Entrada de POST /api/v1/auth/password/reset — ver openapi.yaml.
 *
 * No define `authorize()`: el endpoint es anónimo por diseño, mismo criterio
 * que `LoginRequest` y `RequestPasswordResetRequest`.
 */
class ConfirmPasswordResetRequest extends FormRequest
{
    use NormalizesAccountInput, ResolvesAccountByEmailOrPhone;

    /**
     * Lleva `phone` a su forma canónica, mismo motivo que
     * `RequestPasswordResetRequest`.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->canonicalAccountInput());
    }

    /**
     * La contraseña nueva pasa por la misma política que el registro
     * (`Password::defaults()`): recuperar el acceso no puede ser una vía para
     * terminar con una contraseña más débil que la que el alta habría
     * aceptado.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->accountLookupRules(),
            'code' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'El campo phone debe tener entre 7 y 15 dígitos, con un + opcional al inicio; se guarda siempre con el +.',
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
     * misma clave `code` —celular sin cuenta, sin recuperación pendiente,
     * vencido, intentos agotados, o el valor no coincide—, mismo criterio que
     * `ConfirmPhoneVerificationRequest`: acá además evita que un celular sin
     * cuenta se distinga de uno con cuenta pero sin código pendiente, que
     * sería el mismo oráculo que `RequestPasswordResetAction` ya evita del
     * otro lado.
     */
    private function validateCode(Validator $validator): void
    {
        $reset = $this->pendingReset();

        if ($reset === null) {
            $validator->errors()->add('code', 'No tienes una recuperación de contraseña pendiente; solicita un código nuevo.');

            return;
        }

        if ($reset->expires_at->isPast()) {
            $validator->errors()->add('code', 'El código venció; solicita uno nuevo.');

            return;
        }

        if ($reset->attempts >= Config::integer('password_reset.max_attempts')) {
            $validator->errors()->add('code', 'Superaste el número de intentos permitidos; solicita un código nuevo.');

            return;
        }

        if (! Hash::check($this->string('code')->toString(), $reset->code_hash)) {
            $reset->increment('attempts');
            $validator->errors()->add('code', 'El código no es correcto.');
        }
    }

    private function pendingReset(): ?PasswordResetCode
    {
        return $this->account()?->passwordResetCode;
    }

    public function newPassword(): string
    {
        return $this->string('password')->toString();
    }
}
