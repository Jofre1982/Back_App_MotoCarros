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
            // Historia #17: si el conductor puede recibir solicitudes de
            // viaje cercanas, y su última posición conocida. `latitude`,
            // `longitude` y `location_updated_at` viajan siempre presentes,
            // aunque valgan `null` — mismo criterio que `started_at` en
            // `RideResource`: el contrato los declara nullable justamente
            // para que el cliente no tenga que distinguir "sin ubicación
            // todavía" de "esta respuesta no trae el campo".
            'is_available' => $this->resource->is_available,
            'latitude' => $this->resource->latitude,
            'longitude' => $this->resource->longitude,
            'location_updated_at' => $this->resource->location_updated_at?->toIso8601String(),
        ];
    }
}
