<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el perfil de conductor según el schema `DriverProfile` de
 * openapi.yaml.
 *
 * No lleva el `id` de la fila ni el `user_id`: son claves internas de una
 * relación 1:1 que el cliente nunca necesita nombrar —al perfil se llega por la
 * cuenta, no por su id— y publicarlas invitaría a que alguien intentara
 * direccionarlo por ahí.
 *
 * @property-read DriverProfile $resource
 */
class DriverProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'license_number' => $this->resource->license_number,
        ];
    }
}
