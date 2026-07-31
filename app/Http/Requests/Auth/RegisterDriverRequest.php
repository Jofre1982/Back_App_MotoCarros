<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTOs\DriverRegistration;
use App\Http\Requests\Concerns\NormalizesAccountInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Entrada de POST /api/v1/auth/register/driver — ver openapi.yaml.
 *
 * No define `authorize()`: el endpoint es anónimo por diseño (nadie tiene
 * cuenta todavía cuando lo llama), así que no hay sujeto contra el cual
 * autorizar. Lo que lo protege es el limitador de tasa `auth`, no una Policy.
 */
class RegisterDriverRequest extends FormRequest
{
    use NormalizesAccountInput;

    /**
     * Lleva la entrada a su forma canónica ANTES de validar.
     *
     * `email` y `phone` los normaliza el trait compartido con el registro de
     * pasajero. Acá se suma `license_number`, que tiene el mismo problema por
     * el mismo motivo: `unique` es un `where license_number = ?` sobre una
     * columna con índice único, así que sin normalizar bastaría escribir la
     * licencia en minúsculas para registrarla dos veces y tener dos conductores
     * con la misma habilitación.
     *
     * Solo se recortan los extremos y se pasa a mayúsculas. No se tocan los
     * espacios interiores: un `LIC 445 566` es una licencia mal escrita, y lo
     * correcto es que muera en el `regex` con un 422 explicable, no que el
     * servidor decida por su cuenta cómo debería haberse escrito.
     */
    protected function prepareForValidation(): void
    {
        $canonicos = $this->canonicalAccountInput();

        $license = $this->input('license_number');

        if (is_string($license)) {
            $canonicos['license_number'] = Str::upper(trim($license));
        }

        $this->merge($canonicos);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->accountRules(),
            // `unique` sobre `driver_profiles` y no sobre `users`: la licencia
            // vive en el perfil. Sin la regla, la licencia repetida escalaría a
            // un 500 por violación del índice único en vez del 422 que el
            // conductor puede entender. La Action escribe igual dentro de una
            // transacción, porque entre esta consulta y el INSERT cabe otra
            // alta con la misma licencia.
            'license_number' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9-]{5,50}$/',
                'unique:driver_profiles,license_number',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->accountMessages(),
            'license_number.regex' => 'El campo license number debe tener entre 5 y 50 caracteres, solo letras, dígitos y guiones; se guarda siempre en mayúsculas.',
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
    public function toRegistration(): DriverRegistration
    {
        return new DriverRegistration(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            phone: $this->string('phone')->toString(),
            password: $this->string('password')->toString(),
            licenseNumber: $this->string('license_number')->toString(),
        );
    }
}
