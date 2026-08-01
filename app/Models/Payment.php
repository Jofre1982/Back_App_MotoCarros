<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El cobro de un viaje completado (historia #25).
 *
 * Un viaje tiene a lo sumo un pago: `ChargeRideAction` es la única que
 * escribe esta tabla, y lo hace una sola vez por viaje (ver el índice único
 * de `ride_id` en la migración). No tiene Policy propia — se expone a través
 * del recibo del viaje (`GET /rides/{id}/receipt`, historia #26), bajo la
 * misma autorización que ya resuelve `RidePolicy` para el viaje.
 *
 * `base_fare`, `distance_fare`, `time_fare`, `waiting_fee`, `subtotal` y
 * `minimum_applied` son la `FareBreakdown` que produjo `amount`, persistida
 * tal cual la calculó `CalculateFareAction` al completar el viaje: es lo que
 * el recibo necesita mostrar, y no algo que valga la pena recalcular al leer
 * (ver la migración que agregó estas columnas).
 *
 * @property int $ride_id
 * @property int $amount
 * @property string $currency
 * @property int $base_fare
 * @property int $distance_fare
 * @property int $time_fare
 * @property int $waiting_fee
 * @property int $subtotal
 * @property bool $minimum_applied
 * @property PaymentStatus $status
 * @property Carbon|null $processed_at
 */
#[Fillable([
    'ride_id',
    'amount',
    'currency',
    'base_fare',
    'distance_fare',
    'time_fare',
    'waiting_fee',
    'subtotal',
    'minimum_applied',
    'status',
    'processed_at',
])]
class Payment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'base_fare' => 'integer',
            'distance_fare' => 'integer',
            'time_fare' => 'integer',
            'waiting_fee' => 'integer',
            'subtotal' => 'integer',
            'minimum_applied' => 'boolean',
            'status' => PaymentStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ride, $this>
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}
