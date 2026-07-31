<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\LoginCredentials;
use App\Http\Requests\Concerns\NormalizesAccountInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/auth/login — ver openapi.yaml.
 *
 * No define `authorize()`: el endpoint es anónimo por diseño —autenticarse es
 * justamente lo que viene a hacer quien lo llama—, así que no hay sujeto contra
 * el cual autorizar. Lo que lo protege es el limitador de tasa `auth`.
 */
class LoginRequest extends FormRequest
{
    use NormalizesAccountInput;

    /**
     * Lleva el `email` a su forma canónica ANTES de buscar la cuenta.
     *
     * Usa el mismo trait que los registros a propósito: el email se guardó en
     * minúsculas al darse de alta, así que si el login normalizara distinto —o
     * no normalizara— quien tecleó `Ana@Example.COM` quedaría afuera de su
     * propia cuenta, y encima con un mensaje que le dice que su contraseña está
     * mal. Que la forma canónica viva en un solo lugar es lo que impide que
     * alta y login puedan divergir (ver .claude/STANDARDS.md).
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->canonicalAccountInput());
    }

    /**
     * Solo forma, nunca existencia.
     *
     * No hay `exists:users,email` —sería el oráculo que el 401 genérico existe
     * para evitar— ni `Password::defaults()` sobre la contraseña: aplicar la
     * política del registro haría que una contraseña corta respondiera 422 y
     * una bien formada 401, y esa diferencia de estado delata cuál de los dos
     * campos falló. Una contraseña que no cumple la política simplemente no
     * coincide con ninguna cuenta.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Traduce la entrada validada al DTO que consume la Action, que no conoce
     * HTTP (ver .claude/STANDARDS.md).
     */
    public function toCredentials(): LoginCredentials
    {
        return new LoginCredentials(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
