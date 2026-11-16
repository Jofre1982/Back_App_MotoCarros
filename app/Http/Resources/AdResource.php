<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;

/**
 * Un anuncio como lo ve el admin en el panel: con fechas, estado y los
 * números de vistas y toques.
 *
 * @property-read Ad $resource
 */
class AdResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'business_name' => $this->resource->business_name,
            'message' => $this->resource->message,
            'whatsapp' => $this->resource->whatsapp,
            'image_url' => PublicAdResource::imageUrl($this->resource),
            'starts_on' => $this->resource->starts_on->toDateString(),
            'ends_on' => $this->resource->ends_on->toDateString(),
            'is_paused' => $this->resource->is_paused,
            'status' => $this->status(),
            'views' => $this->resource->views,
            'clicks' => $this->resource->clicks,
        ];
    }

    private function status(): string
    {
        $today = Date::now(Ad::TIMEZONE)->toDateString();

        return match (true) {
            $this->resource->ends_on->toDateString() < $today => 'expired',
            $this->resource->is_paused => 'paused',
            $this->resource->starts_on->toDateString() > $today => 'scheduled',
            default => 'active',
        };
    }
}
