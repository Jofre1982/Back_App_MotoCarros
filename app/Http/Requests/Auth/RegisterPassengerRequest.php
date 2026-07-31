<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\PassengerRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Entrada de POST /api/v1/auth/register/passenger — ver openapi.yaml.
 *
 * No define `authorize()`: el endpoint es anónimo por diseño (nadie tiene
 * cuenta todavía cuando lo llama), así que no hay sujeto contra el cual
 * autorizar. Lo que lo protege es el limitador de tasa `auth`, no una Policy.
 */
class RegisterPassengerRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // `unique` acá y no solo el índice de la tabla: sin la regla, un
            // teléfono repetido escalaría a un 500 por violación de constraint
            // en vez del 422 que el cliente puede mostrarle al usuario.
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{7,15}$/', 'unique:users,phone'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'El campo phone debe tener entre 7 y 15 dígitos, con un + opcional al inicio.',
        ];
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
