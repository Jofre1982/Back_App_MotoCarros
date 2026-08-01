<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\RideCancellation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el resultado de cancelar un viaje según el schema de la
 * respuesta 200 de `POST /rides/{id}/cancel` en openapi.yaml: los mismos
 * campos que `RideResource` más `cancellation_fee_applies`.
 *
 * Reutiliza `RideResource` para el viaje en vez de repetir el mapeo de
 * campos: esta clase solo agrega el campo que `RideResource` no conoce.
 *
 * @property-read RideCancellation $resource
 */
class RideCancellationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new RideResource($this->resource->ride))->toArray($request),
            'cancellation_fee_applies' => $this->resource->feeApplies,
        ];
    }
}
