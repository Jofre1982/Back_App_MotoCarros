<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\CargoJobTypePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un tipo de trabajo de Motocarga con precio fijo (historia técnica #86),
 * ej. "Acarreo" ($20.000), "Escombro" ($40.000). A diferencia del precio de
 * pasajero por sitio (`SiteFare`, #85), el precio de carga depende de qué se
 * transporta y no de a dónde va — así lo describió el dueño del producto: el
 * precio de un trasteo dentro del pueblo no depende del sitio de destino.
 *
 * @property string $name
 * @property int $price
 */
#[Fillable(['name', 'price'])]
#[UsePolicy(CargoJobTypePolicy::class)]
class CargoJobType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }
}
