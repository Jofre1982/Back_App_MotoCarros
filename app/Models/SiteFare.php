<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PricingUnit;
use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El precio fijo de pasajero de un sitio, para un tipo de vehículo (historia
 * técnica #85). Único por `(site_id, vehicle_type)`: un sitio tiene a lo sumo
 * un precio de Motocarro y uno de Motocarga.
 *
 * @property int $site_id
 * @property VehicleType $vehicle_type
 * @property PricingUnit $pricing_unit
 * @property int $day_price
 * @property int|null $night_price
 */
#[Fillable(['site_id', 'vehicle_type', 'pricing_unit', 'day_price', 'night_price'])]
class SiteFare extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'pricing_unit' => PricingUnit::class,
            'day_price' => 'integer',
            'night_price' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
