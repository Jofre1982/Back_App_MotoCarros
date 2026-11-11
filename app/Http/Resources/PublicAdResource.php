<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un anuncio como lo ve el pasajero: sin fechas ni números.
 *
 * @property-read Ad $resource
 */
class PublicAdResource extends JsonResource
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
            'image_url' => self::imageUrl($this->resource),
        ];
    }

    public static function imageUrl(Ad $ad): ?string
    {
        return $ad->image_path === null ? null : route('ads.image', $ad);
    }
}
