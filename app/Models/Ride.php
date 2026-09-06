<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RatedRole;
use App\Enums\RideStatus;
use App\Policies\RidePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Un viaje solicitado por un pasajero.
 *
 * Guarda el sitio de destino y la tarifa que se fijaron al crearlo: el
 * pasajero aceptó esos números, así que son parte del viaje y no algo que se
 * recalcule al consultarlo. El cobro final se resuelve al completarlo
 * (historia #24) — y desde la historia #87 es siempre igual a
 * `estimated_fare`, porque el precio es fijo por sitio y no depende de la
 * distancia/tiempo realmente recorridos.
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
 * @property int $destination_site_id
 * @property int $passenger_count
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
    'destination_site_id',
    'passenger_count',
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
            'passenger_count' => 'integer',
            'estimated_fare' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'final_fare' => 'integer',
        ];
    }

    /**
     * El sitio elegido como destino (historia #87). Ver `SiteFare` para el
     * precio que se le fijó a este viaje.
     *
     * @return BelongsTo<Site, $this>
     */
    public function destinationSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'destination_site_id');
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

    /**
     * El cobro del viaje, o `null` hasta que se completa (historia #25).
     *
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * La calificación que el pasajero dio al conductor, o `null` mientras no
     * la haya registrado (historia #27).
     *
     * @return HasOne<RideRating, $this>
     */
    public function driverRating(): HasOne
    {
        return $this->hasOne(RideRating::class)->where('rated_role', RatedRole::Driver);
    }
}
