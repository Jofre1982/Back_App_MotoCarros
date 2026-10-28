<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ErrandStatus;
use App\Enums\VehicleType;
use App\Policies\ErrandPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un mandado (servicio a domicilio) pedido por un pasajero (historia #92).
 *
 * A diferencia de `Ride`, no guarda ninguna tarifa: el precio se negocia por
 * fuera y el conductor lo registra al aceptar (`agreed_price`), así que nace
 * en `null` y no hay nada que recalcular al completarlo.
 *
 * @property ErrandStatus $status
 * @property int $passenger_id
 * @property int|null $driver_id
 * @property string $description
 * @property string|null $photo_path
 * @property float $origin_latitude
 * @property float $origin_longitude
 * @property VehicleType $vehicle_type
 * @property int|null $destination_site_id
 * @property string|null $destination_text
 * @property int|null $agreed_price
 * @property Carbon|null $price_confirmed_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'passenger_id',
    'driver_id',
    'status',
    'description',
    'photo_path',
    'origin_latitude',
    'origin_longitude',
    'vehicle_type',
    'destination_site_id',
    'destination_text',
    'agreed_price',
    'price_confirmed_at',
    'completed_at',
])]
#[UsePolicy(ErrandPolicy::class)]
class Errand extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ErrandStatus::class,
            'vehicle_type' => VehicleType::class,
            'origin_latitude' => 'float',
            'origin_longitude' => 'float',
            'agreed_price' => 'integer',
            'price_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    /**
     * El conductor asignado, o `null` mientras nadie haya aceptado el
     * mandado.
     *
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * El sitio de entrega (historia #85). El punto de recogida, en cambio,
     * es libre — ver `origin_latitude`/`origin_longitude`.
     *
     * @return BelongsTo<Site, $this>
     */
    public function destinationSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'destination_site_id');
    }

    public function isPriceConfirmed(): bool
    {
        return $this->price_confirmed_at !== null;
    }
}
