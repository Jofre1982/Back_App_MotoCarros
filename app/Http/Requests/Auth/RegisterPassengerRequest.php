<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\PassengerRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
     * Lleva `email` y `phone` a su forma canónica ANTES de validar.
     *
     * `unique` traduce a un `where columna = ?`, así que sin esto la unicidad de
     * la cuenta queda a merced de la colación del motor: en SQLite (el driver de
     * dev y el de `phpunit.xml`) es BINARY, y `Ana@example.com` convive con
     * `ana@example.com` como dos cuentas distintas; en MySQL con colación `_ci`
     * el comportamiento sería el contrario. Normalizar acá hace que lo que se
     * valida y lo que se guarda sean siempre el mismo valor comparable, sin
     * depender del motor — y sobre esa unicidad se apoyan el login (#8) y
     * cualquier recuperación de cuenta futura.
     */
    protected function prepareForValidation(): void
    {
        $canonicos = [];

        $email = $this->input('email');

        if (is_string($email)) {
            $canonicos['email'] = Str::lower($email);
        }

        $phone = $this->input('phone');

        // El `+` es opcional en la entrada, así que `573001234567` y
        // `+573001234567` son el mismo número y tienen que colisionar: no hace
        // falta mala intención para mandar los dos, es la diferencia entre
        // teclearlo y pegarlo desde la agenda. Solo se antepone el `+` a lo que
        // ya empieza por dígito, para que una entrada malformada (`++57…`) siga
        // muriendo en el `regex` en vez de quedar "arreglada" por acá.
        if (is_string($phone) && preg_match('/^[0-9]/', $phone) === 1) {
            $canonicos['phone'] = '+'.$phone;
        }

        $this->merge($canonicos);
    }

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
            // El regex exige el `+` porque para cuando corre ya se normalizó la
            // entrada: la forma canónica siempre lo lleva. Lo que el cliente
            // manda sigue siendo `+` opcional (ver `prepareForValidation`).
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+[0-9]{7,15}$/', 'unique:users,phone'],
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
