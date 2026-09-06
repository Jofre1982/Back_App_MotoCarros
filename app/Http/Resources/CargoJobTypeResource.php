<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CargoJobType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un tipo de acarreo (historia técnica #86). Lleva `id` — es por
 * lo que se lo referencia en `/admin/cargo-job-types/{cargoJobType}`.
 *
 * @property-read CargoJobType $resource
 */
class CargoJobTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'price' => $this->resource->price,
        ];
    }
}
