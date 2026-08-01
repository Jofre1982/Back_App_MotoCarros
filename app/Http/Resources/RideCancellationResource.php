<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\RideCancellation;
use App\Enums\RideStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el resultado de `POST /rides/{id}/cancel` según el schema de su
 * respuesta 200 en openapi.yaml: los mismos campos que `RideResource` más
 * `cancellation_fee_applies` — pero solo cuando el viaje quedó `cancelled`.
 * Si en cambio volvió a `requested` (el conductor asignado lo devolvió al
 * pool, historia #23), ese campo no aplica y se omite.
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
            'cancellation_fee_applies' => $this->when(
                $this->resource->ride->status === RideStatus::Cancelled,
                $this->resource->feeApplies,
            ),
        ];
    }
}
