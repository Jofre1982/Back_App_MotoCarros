<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Forma canónica y reglas de los campos de cuenta comunes a todo registro.
 *
 * Vive en un solo lugar porque `.claude/STANDARDS.md` exige que los endpoints
 * de registro normalicen igual: si el de pasajero y el de conductor
 * divergieran, la misma persona podría quedar con dos cuentas —una por rol—
 * usando el mismo email escrito distinto, y sobre esa unicidad se apoyan el
 * login (#8) y la recuperación de cuenta.
 */
trait NormalizesAccountInput
{
    /**
     * Lleva `email` y `phone` a su forma canónica, para mezclar en
     * `prepareForValidation()` — es decir, ANTES de `unique` y del DTO.
     *
     * `unique` traduce a un `where columna = ?`, así que sin esto la unicidad
     * de la cuenta queda a merced de la colación del motor: en SQLite (el
     * driver de dev y el de `phpunit.xml`) es BINARY, y `Ana@example.com`
     * convive con `ana@example.com` como dos cuentas distintas; en MySQL con
     * colación `_ci` el comportamiento sería el contrario. Normalizar acá hace
     * que lo que se valida y lo que se guarda sean siempre el mismo valor
     * comparable, sin depender del motor.
     *
     * @return array<string, string>
     */
    protected function canonicalAccountInput(): array
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

        return $canonicos;
    }

    /**
     * Reglas de los campos que toda cuenta tiene, sin importar el rol.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function accountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // `unique` acá y no solo el índice de la tabla: sin la regla, un
            // teléfono repetido escalaría a un 500 por violación de constraint
            // en vez del 422 que el cliente puede mostrarle al usuario.
            // El regex exige el `+` porque para cuando corre ya se normalizó la
            // entrada: la forma canónica siempre lo lleva. Lo que el cliente
            // manda sigue siendo `+` opcional (ver `canonicalAccountInput`).
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+[0-9]{7,15}$/', 'unique:users,phone'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function accountMessages(): array
    {
        return [
            'phone.regex' => 'El campo phone debe tener entre 7 y 15 dígitos, con un + opcional al inicio; se guarda siempre con el +.',
        ];
    }
}
