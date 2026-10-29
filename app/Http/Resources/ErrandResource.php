<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un mandado según el schema `Errand` de openapi.yaml.
 *
 * Publica el `id` porque el mandado se direcciona por él (aceptar,
 * completar, ver la foto), mismo criterio que `RideResource`. No publica
 * `photo_path`: es una ruta de disco interna, el cliente solo necesita
 * `has_photo` y `photo_url` para decidir si pedirla.
 *
 * @property-read Errand $resource
 */
class ErrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status->value,
            'description' => $this->resource->description,
            'has_photo' => $this->resource->photo_path !== null,
            'photo_url' => $this->resource->photo_path === null
                ? null
                : route('errands.photo', $this->resource),
            'origin' => [
                'latitude' => $this->resource->origin_latitude,
                'longitude' => $this->resource->origin_longitude,
            ],
            'vehicle_type' => $this->resource->vehicle_type->value,
            'destination' => $this->resource->destinationSite === null ? null : [
                'site_id' => $this->resource->destinationSite->id,
                'name' => $this->resource->destinationSite->name,
            ],
            'destination_text' => $this->resource->destination_text,
            // Presente siempre, aunque valga `null`, mismo criterio que
            // `driver`/`final_fare` en `RideResource`: el contrato lo declara
            // nullable para que el cliente no distinga "todavía sin
            // conductor/precio" de "esta respuesta no lo trae".
            'driver' => $this->resource->driver === null
                ? null
                : new RideDriverResource($this->resource->driver),
            'agreed_price' => $this->resource->agreed_price,
            'price_confirmed' => $this->resource->isPriceConfirmed(),
            'counterpart' => CounterpartResource::for(
                $request->user(),
                $this->resource->passenger,
                $this->resource->driver,
                $this->resource->isPriceConfirmed() && $this->resource->status === ErrandStatus::Accepted,
            ),
            'requested_at' => $this->resource->created_at?->toIso8601String(),
            'completed_at' => $this->resource->completed_at?->toIso8601String(),
        ];
    }
}
