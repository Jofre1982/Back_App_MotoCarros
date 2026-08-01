<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un viaje según el schema `Ride` de openapi.yaml.
 *
 * Publica el `id` —al revés que el vehículo y el perfil del conductor— porque
 * el viaje sí se direcciona por él: es el `{rideId}` del canal privado
 * `ride.{rideId}` y el que van a llevar los endpoints de aceptar, iniciar y
 * completar. No publica el `passenger_id`: el viaje se solicita siempre desde la
 * cuenta que manda el token.
 *
 * @property-read Ride $resource
 */
class RideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status->value,
            'origin' => [
                'latitude' => $this->resource->origin_latitude,
                'longitude' => $this->resource->origin_longitude,
            ],
            'destination' => [
                'latitude' => $this->resource->destination_latitude,
                'longitude' => $this->resource->destination_longitude,
            ],
            'distance_meters' => $this->resource->estimated_distance_meters,
            'duration_seconds' => $this->resource->estimated_duration_seconds,
            'currency' => $this->resource->currency,
            'estimated_fare' => $this->resource->estimated_fare,
            // ISO-8601 explícito en vez del formato por defecto de Eloquent:
            // el contrato publica `format: date-time` y el default trae
            // microsegundos que nadie usa acá.
            'requested_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
