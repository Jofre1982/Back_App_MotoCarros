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
     * El `regex` de la contraseña no es una excepción a esa regla, es la misma:
     * rechaza el byte nulo, que es forma y no existencia. `password_hash()` no
     * lo acepta —lanza un `ValueError` que `BcryptHasher::make()` re-lanza como
     * `RuntimeException`—, así que sin esta regla la contraseña `"a\0b"` salía
     * 500 contra un email sin cuenta (por el hash de descarte de la Action) y
     * 401 contra uno registrado (`password_verify()` no lanza, devuelve
     * `false`): el estado pasaba a depender de si el email existía. Un byte nulo
     * tampoco puede ser la contraseña de ninguna cuenta —el alta habría fallado
     * igual al hashearla—, así que su 422 no depende de qué haya en la base, el
     * mismo argumento que el del `email` mal formado.
     *
     * Deliberadamente NO hay un `max` sobre la contraseña: `Password::defaults()`
     * no le pone techo a la del registro, así que acotarla solo acá dejaría
     * afuera de su propia cuenta a quien se registró con una más larga. Y no
     * hace falta para el 500: bcrypt trunca a 72 bytes sin lanzar nada.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'regex:/^[^\x00]*$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        // El mensaje por defecto de `regex` ("el formato es inválido") invita a
        // creer que hay una política de formato que cumplir, que es justo lo que
        // el login no tiene.
        return [
            'password.regex' => 'El campo password no puede contener un byte nulo.',
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
