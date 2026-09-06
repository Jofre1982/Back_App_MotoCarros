<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesAccountInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/auth/password/forgot — ver openapi.yaml.
 *
 * No define `authorize()`: el endpoint es anónimo por diseño, mismo criterio
 * que `LoginRequest`. Tampoco valida que el celular tenga cuenta —`exists:`
 * sería el oráculo que `RequestPasswordResetAction` existe para evitar—: la
 * regla solo comprueba la forma, igual que el email en el login.
 */
class RequestPasswordResetRequest extends FormRequest
{
    use NormalizesAccountInput;

    /**
     * Lleva `phone` a su forma canónica ANTES de validar, mismo motivo que en
     * el login: si esta entrada normalizara distinto que el registro, un
     * celular guardado como `+57...` no coincidiría con uno tecleado sin el
     * `+`, y la cuenta "no encontrada" quedaría indistinguible de una
     * realmente inexistente por el motivo equivocado.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->canonicalAccountInput());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+[0-9]{7,15}$/'],
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

    public function phone(): string
    {
        return $this->string('phone')->toString();
    }
}
