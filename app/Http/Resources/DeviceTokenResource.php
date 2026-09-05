<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el token de dispositivo recién registrado.
 *
 * No lleva el `token` en sí ni el `id` de la fila: el cliente ya sabe cuál es
 * su propio token (lo mandó), y el `id` no sirve para nada en esta respuesta
 * —no hay ningún endpoint que direccione por él—, mismo criterio que
 * `VehicleResource`.
 *
 * @property-read DeviceToken $resource
 */
class DeviceTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'platform' => $this->resource->platform->value,
            'registered_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
