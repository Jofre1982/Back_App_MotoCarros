<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa una cuenta según el schema `User` de openapi.yaml.
 *
 * Los campos se listan de forma explícita en vez de volcar el modelo: `$hidden`
 * protege la contraseña, pero cualquier columna que se agregue mañana a `users`
 * quedaría expuesta sola si acá se devolviera el modelo entero.
 *
 * @property-read User $resource
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'phone_verified' => $this->resource->isPhoneVerified(),
            'role' => $this->resource->role->value,
        ];
    }
}
