<?php

declare(strict_types=1);

namespace App\Http\Requests\Realtime;

use App\DTOs\DeviceTokenRegistration;
use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Entrada de POST /api/v1/me/device-token — ver openapi.yaml.
 *
 * No define `authorize()`: el recurso *es* la cuenta autenticada (el token se
 * asocia a quien lo registra, no a un id de la ruta), así que no hay una
 * pregunta de autorización distinta de "¿tiene un token válido?", que ya
 * resuelve el middleware `auth:api`. No es una operación de un rol en
 * particular: pasajero y conductor pueden tener un dispositivo (mismo
 * criterio que `UpdateProfileRequest`).
 */
class RegisterDeviceTokenRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', new Enum(DevicePlatform::class)],
        ];
    }

    public function toRegistration(): DeviceTokenRegistration
    {
        return new DeviceTokenRegistration(
            token: $this->string('token')->toString(),
            platform: DevicePlatform::from($this->string('platform')->toString()),
        );
    }
}
