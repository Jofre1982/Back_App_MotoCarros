<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el vehículo según el schema `Vehicle` de openapi.yaml.
 *
 * No lleva el `id` de la fila ni el `user_id`, por el mismo criterio que
 * `DriverProfileResource`: son claves internas de una relación 1:1 a la que se
 * llega por la cuenta que manda el token, nunca por un id propio, y publicarlas
 * invitaría a intentar direccionar el vehículo por ahí.
 *
 * @property-read Vehicle $resource
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'plate' => $this->resource->plate,
            'type' => $this->resource->type->value,
            'year' => $this->resource->year,
        ];
    }
}
