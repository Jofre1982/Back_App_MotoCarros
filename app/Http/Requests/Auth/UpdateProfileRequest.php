<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\ProfileUpdate;
use App\Http\Requests\Concerns\NormalizesAccountInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrada de PATCH /api/v1/me — ver openapi.yaml.
 *
 * No define `authorize()`: el recurso *es* la cuenta autenticada (la resuelve
 * el guard, no un id de la ruta), así que no hay una pregunta de autorización
 * distinta de "¿tiene un token válido?", que ya resuelve el middleware
 * `auth:api`.
 */
class UpdateProfileRequest extends FormRequest
{
    use NormalizesAccountInput;

    /**
     * Lleva `email` y `phone` a su forma canónica ANTES de validar, igual que
     * el registro — el porqué está en `NormalizesAccountInput`. Solo toca los
     * campos que de verdad vinieron: `canonicalAccountInput()` ya se salta los
     * que faltan, que es justo lo que este PATCH parcial necesita.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->canonicalAccountInput());
    }

    /**
     * Todas las reglas van con `sometimes|required`: es un PATCH parcial, así
     * que un campo ausente no es un error, es "no lo toques"; pero uno presente
     * no puede venir vacío. `sometimes` a secas no alcanza —al contrario, sería
     * un agujero—: ante una cadena vacía Laravel se salta TODAS las reglas no
     * implícitas (`Validator::presentOrRuleIsImplicit()`), incluidas `email`,
     * `regex` y `unique`, así que `{"email": ""}` pasaría la validación y
     * borraría el email de la cuenta. Y `users.email`/`users.phone` son NOT
     * NULL UNIQUE, con lo que la segunda cuenta que lo hiciera reventaría el
     * índice con un 500. `required` es implícita, corre igual sobre la cadena
     * vacía y también cubre la que trae solo espacios.
     *
     * La unicidad de email y phone se ignora contra la propia cuenta —si no,
     * reenviar el mismo valor sin cambiarlo respondería 422 contra uno mismo.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $usuario = $this->user();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($usuario->getKey()),
            ],
            'phone' => [
                'sometimes', 'required', 'string', 'max:20', 'regex:/^\+[0-9]{7,15}$/',
                Rule::unique('users', 'phone')->ignore($usuario->getKey()),
            ],
        ];
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
     * Los campos se leen uno por uno y no con `validated()`: es lo que
     * garantiza que nada que no esté acá —un `role` o un `id` mandado por el
     * cliente, por ejemplo— pueda colarse hasta la Action. Se lee con `has()`
     * y no con `filled()` porque lo que importa es si el campo vino en la
     * request: para cuando esto corre, un valor vacío ya murió en el
     * `required` de `rules()`, así que lo que llega es un valor real.
     */
    public function toUpdate(): ProfileUpdate
    {
        return new ProfileUpdate(
            name: $this->has('name') ? $this->string('name')->toString() : null,
            email: $this->has('email') ? $this->string('email')->toString() : null,
            phone: $this->has('phone') ? $this->string('phone')->toString() : null,
        );
    }
}
