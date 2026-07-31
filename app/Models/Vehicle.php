<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\VehiclePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La moto con la que trabaja un conductor. Relación 1:1 con `User`, sostenida
 * por el índice único de `user_id`.
 *
 * La Policy se declara con el atributo en vez de dejarla a la convención de
 * nombres: acá es la que decide el 403 del pasajero que intenta registrar un
 * vehículo, y una autorización que depende de que nadie renombre la clase es
 * más frágil de lo que conviene.
 *
 * @property int $year
 */
#[Fillable(['user_id', 'plate', 'model', 'year'])]
#[UsePolicy(VehiclePolicy::class)]
class Vehicle extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
