<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DriverDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el documento recién subido. No lleva el `id` de la fila ni el
 * `driver_profile_id`, mismo criterio que `VehicleResource`: se llega al
 * documento por la cuenta del token, nunca por un id propio.
 *
 * @property-read DriverDocument $resource
 */
class DriverDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->resource->type->value,
            'status' => $this->resource->status->value,
            'uploaded_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
