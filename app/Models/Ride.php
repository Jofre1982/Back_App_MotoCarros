<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RideStatus;
use App\Policies\RidePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un viaje solicitado por un pasajero.
 *
 * Guarda el trayecto y la tarifa que se estimaron al crearlo: el pasajero
 * aceptó esos números, así que son parte del viaje y no algo que se recalcule
 * al consultarlo. El cobro final se resuelve al completarlo (historia #24).
 *
 * `active_passenger_id` y `active_driver_id` no aparecen acá a propósito: son
 * columnas generadas por la base (ver las migraciones) y escribirlas desde la
 * aplicación no tendría efecto más que romper el INSERT/UPDATE.
 *
 * Los atributos con cast se declaran en el docblock porque su tipo real no es
 * el de la columna (ver .claude/STANDARDS.md).
 *
 * @property RideStatus $status
 * @property int $passenger_id
 * @property int|null $driver_id
 * @property float $origin_latitude
 * @property float $origin_longitude
 * @property float $destination_latitude
 * @property float $destination_longitude
 * @property int $estimated_distance_meters
 * @property int $estimated_duration_seconds
 * @property int $estimated_fare
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $final_fare
 */
#[Fillable([
    'passenger_id',
    'driver_id',
    'status',
    'started_at',
    'completed_at',
    'final_fare',
    'origin_latitude',
    'origin_longitude',
    'destination_latitude',
    'destination_longitude',
    'estimated_distance_meters',
    'estimated_duration_seconds',
    'currency',
    'estimated_fare',
])]
#[UsePolicy(RidePolicy::class)]
class Ride extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RideStatus::class,
            'origin_latitude' => 'float',
            'origin_longitude' => 'float',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
            'estimated_distance_meters' => 'integer',
            'estimated_duration_seconds' => 'integer',
            'estimated_fare' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'final_fare' => 'integer',
        ];
    }

    /**
     * Quien pidió el viaje.
     *
     * La relación se llama `passenger` y no `user` porque del otro lado hay dos
     * cuentas distintas —el pasajero y el conductor asignado— y ambas son
     * `users`: un nombre genérico acá haría ambigua cualquier consulta.
     *
     * @return BelongsTo<User, $this>
     */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    /**
     * El conductor asignado, o `null` mientras nadie haya aceptado el viaje.
     *
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
