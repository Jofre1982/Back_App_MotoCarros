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
 * de `ride_id` en la migración). No tiene Policy propia porque no se expone
 * por ningún endpoint todavía — se consulta a través de `Ride::payment()`,
 * bajo la misma autorización que ya resuelve `RidePolicy` para el viaje.
 *
 * @property int $ride_id
 * @property int $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property Carbon|null $processed_at
 */
#[Fillable([
    'ride_id',
    'amount',
    'currency',
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
