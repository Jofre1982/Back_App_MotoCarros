<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\RideEstimate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa una estimación de viaje según el schema `RideEstimate` de
 * openapi.yaml. Desde la historia #87 el destino es un sitio con precio
 * fijo, no un trayecto calculado — ya no lleva `distance_meters`/
 * `duration_seconds`.
 *
 * @property-read RideEstimate $resource
 */
class RideEstimateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'destination' => [
                'site_id' => $this->resource->destinationSite->id,
                'name' => $this->resource->destinationSite->name,
            ],
            'passenger_count' => $this->resource->passengerCount,
            'currency' => $this->resource->currency,
            'estimated_fare' => $this->resource->fare,
            // No representa un cobro: el pasajero solo lo usa para decidir si
            // continúa, el monto final se recalcula al completar el viaje
            // (historia #24). Se declara acá en vez de dejar que el nombre del
            // campo lo insinúe, porque el issue #14 lo pide explícito.
            'is_estimate' => true,
        ];
    }
}
