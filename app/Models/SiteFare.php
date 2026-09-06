<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PricingUnit;
use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    /**
     * El monto que corresponde cobrar a la hora `$at`: el recargo nocturno
     * (de 10pm a 5am, confirmado con el dueño del producto) solo aplica si
     * el sitio tiene uno definido — hoy solo el "Casco urbano" lo tiene. Un
     * sitio sin `night_price` cobra siempre el precio de día, sin importar
     * la hora.
     *
     * La ventana cruza medianoche (22:00 → 05:00 del día siguiente), así que
     * no alcanza una sola comparación de `hour`: son dos tramos del mismo
     * reloj de 24h.
     */
    public function priceAt(Carbon $at): int
    {
        if ($this->night_price !== null && ($at->hour >= 22 || $at->hour < 5)) {
            return $this->night_price;
        }

        return $this->day_price;
    }
}
