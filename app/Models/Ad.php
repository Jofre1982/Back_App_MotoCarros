<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\AdPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Anuncio de un negocio local. Lo ve el pasajero mientras espera su
 * motocarro, entre `starts_on` y `ends_on` (fechas de Colombia), si no está
 * en pausa. `views`/`clicks` son los números que el dueño le muestra al
 * negocio para que renueve.
 *
 * @property int $id
 * @property string $business_name
 * @property string $message
 * @property string|null $whatsapp
 * @property string|null $image_path
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $is_paused
 * @property int $views
 * @property int $clicks
 */
#[Fillable(['business_name', 'message', 'whatsapp', 'image_path', 'starts_on', 'ends_on', 'is_paused'])]
#[UsePolicy(AdPolicy::class)]
class Ad extends Model
{
    use HasFactory;

    /** Las fechas de los anuncios se cuentan en la hora de Colombia. */
    public const string TIMEZONE = 'America/Bogota';

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_paused' => 'boolean',
            'views' => 'integer',
            'clicks' => 'integer',
        ];
    }

    /**
     * Los que están saliendo ese día.
     *
     * @param  Builder<Ad>  $query
     */
    public function scopeShowingOn(Builder $query, string $day): void
    {
        $query->where('is_paused', false)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day);
    }
}
