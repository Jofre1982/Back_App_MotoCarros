<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un sitio con sus precios (historia técnica #85). A diferencia
 * de `VehicleResource`, sí lleva `id` — es por lo que se lo referencia en
 * `/admin/sites/{site}/fare` y, más adelante (#87), en la creación del
 * viaje.
 *
 * El controller siempre precarga `fares` antes de construir este recurso.
 *
 * @property-read Site $resource
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'fares' => SiteFareResource::collection($this->resource->fares),
        ];
    }
}
