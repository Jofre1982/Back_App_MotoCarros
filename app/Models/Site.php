<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\SitePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un destino con nombre (historia técnica #85), ej. "Casco urbano",
 * "Aeropuerto", "Vitina". El admin lo administra desde el panel; el pasajero
 * lo va a elegir de una lista en vez de marcar un punto libre en el mapa
 * (historia #87, todavía sin implementar).
 *
 * @property string $name
 */
#[Fillable(['name'])]
#[UsePolicy(SitePolicy::class)]
class Site extends Model
{
    use HasFactory;

    public function fares(): HasMany
    {
        return $this->hasMany(SiteFare::class);
    }
}
