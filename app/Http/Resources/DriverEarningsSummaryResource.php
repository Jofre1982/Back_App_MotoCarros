<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\DriverEarningsSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un resumen de ganancias de conductor según el schema
 * `DriverEarningsSummary` de openapi.yaml.
 *
 * @property-read DriverEarningsSummary $resource
 */
class DriverEarningsSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from' => $this->resource->from->toDateString(),
            'to' => $this->resource->to->toDateString(),
            'currency' => $this->resource->currency,
            'total_earned' => $this->resource->totalEarned,
            'completed_rides' => $this->resource->completedRides,
        ];
    }
}
