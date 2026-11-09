<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\VehicleType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un conductor favorito del pasajero — ver `FavoriteDriver` en openapi.yaml.
 *
 * @property User $resource
 */
class FavoriteDriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vehicle = $this->resource->vehicle;

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'avatar_url' => AvatarUrl::forOthers($this->resource),
            'vehicle' => $vehicle === null ? null : [
                'plate' => $vehicle->plate,
                'type' => $vehicle->type->value,
            ],
            // Si se le puede pedir un viaje ya: tiene "Recibir viajes"
            // encendido y su vehículo no es motocarga.
            'receiving_rides' => $this->resource->driverProfile?->is_available === true
                && $vehicle?->type !== VehicleType::Motocarga,
        ];
    }
}
