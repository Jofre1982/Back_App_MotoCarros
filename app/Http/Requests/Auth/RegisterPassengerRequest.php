<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\PassengerRegistration;
use App\Http\Requests\Concerns\NormalizesAccountInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/auth/register/passenger — ver openapi.yaml.
 *
 * No define `authorize()`: el endpoint es anónimo por diseño (nadie tiene
 * cuenta todavía cuando lo llama), así que no hay sujeto contra el cual
 * autorizar. Lo que lo protege es el limitador de tasa `auth`, no una Policy.
 */
class RegisterPassengerRequest extends FormRequest
{
    use NormalizesAccountInput;

    /**
     * Lleva `email` y `phone` a su forma canónica ANTES de validar — el porqué
     * está en `NormalizesAccountInput`, compartido con el registro de conductor
     * para que ambas altas no puedan divergir.
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
        return $this->accountRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->accountMessages();
    }

    /**
     * Traduce la entrada validada al DTO que consume la Action, que no conoce
     * HTTP (ver .claude/STANDARDS.md).
     *
     * Los campos se leen uno por uno en vez de con `validated()`: es lo que
     * garantiza que nada que no esté acá —un `role` mandado por el cliente, por
     * ejemplo— pueda colarse hasta la creación del usuario.
     */
    public function toRegistration(): PassengerRegistration
    {
        return new PassengerRegistration(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            phone: $this->string('phone')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
