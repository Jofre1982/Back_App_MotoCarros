<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Models\Ad;

/**
 * Suma una vista: la app la reporta la primera vez que le muestra el
 * anuncio a un pasajero mientras espera un viaje.
 */
final class RecordAdViewAction
{
    public function handle(Ad $ad): void
    {
        $ad->increment('views');
    }
}
