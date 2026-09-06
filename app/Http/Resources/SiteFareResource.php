<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SiteFare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el precio de un sitio para un tipo de vehículo (historia
 * técnica #85). No lleva `id` propio: se referencia siempre por
 * `(site, vehicle_type)`, nunca por un id de fila.
 *
 * @property-read SiteFare $resource
 */
class SiteFareResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'vehicle_type' => $this->resource->vehicle_type->value,
            'pricing_unit' => $this->resource->pricing_unit->value,
            'day_price' => $this->resource->day_price,
            'night_price' => $this->resource->night_price,
        ];
    }
}
