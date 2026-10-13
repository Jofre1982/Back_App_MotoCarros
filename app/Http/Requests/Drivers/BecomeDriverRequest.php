<?php

declare(strict_types=1);

namespace App\Http\Requests\Drivers;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Entrada de POST /api/v1/me/driver-profile — ver openapi.yaml (historia #99).
 *
 * `license_number` sigue las mismas reglas que en `RegisterDriverRequest`:
 * es el mismo dato, solo que pedido a una cuenta que ya existe.
 */
class BecomeDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DriverProfile::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $license = $this->input('license_number');

        if (is_string($license)) {
            $this->merge(['license_number' => Str::upper(trim($license))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
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
            'license_number.regex' => 'El campo license number debe tener entre 5 y 50 caracteres, solo letras, dígitos y guiones; se guarda siempre en mayúsculas.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [$this->rejectExistingDriverProfile(...)];
    }

    /**
     * Ya tener perfil de conductor es 422 bajo la clave `driver_profile`, que
     * no es un campo de la entrada: no lo decide lo que manda el cliente sino
     * el estado de su cuenta (mismo criterio que el segundo vehículo).
     */
    private function rejectExistingDriverProfile(Validator $validator): void
    {
        $user = $this->user();

        if ($user instanceof User && $user->driverProfile()->exists()) {
            $validator->errors()->add('driver_profile', 'Tu cuenta ya tiene el modo conductor activo.');
        }
    }

    public function licenseNumber(): string
    {
        return $this->string('license_number')->toString();
    }
}
