<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RideRating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa la calificación de un viaje según el schema `RideRating` de
 * openapi.yaml (historia #27).
 *
 * @property-read RideRating $resource
 */
class RideRatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ride_id' => $this->resource->ride_id,
            'score' => $this->resource->score,
            'comment' => $this->resource->comment,
            'rated_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
