<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RatedRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La calificación de una de las dos partes de un viaje completado, dada por
 * la otra: al conductor (historia #27) o al pasajero (historia #28).
 *
 * A lo sumo una fila por `ride_id` y `rated_role` (ver el índice único de la
 * migración): quién calificó y a quién se deducen del viaje —`passenger_id`
 * y `driver_id`— y no se repiten acá.
 *
 * @property int $ride_id
 * @property RatedRole $rated_role
 * @property int $score
 * @property string|null $comment
 */
#[Fillable(['ride_id', 'rated_role', 'score', 'comment'])]
class RideRating extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rated_role' => RatedRole::class,
            'score' => 'integer',
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
