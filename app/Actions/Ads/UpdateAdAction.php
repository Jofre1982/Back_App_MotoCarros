<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Models\Ad;

/**
 * Cambia los datos de un anuncio, o lo pausa / reanuda.
 */
final class UpdateAdAction
{
    /**
     * @param  array{business_name?: string, message?: string, whatsapp?: string|null, starts_on?: string, ends_on?: string, is_paused?: bool}  $attributes
     */
    public function handle(Ad $ad, array $attributes): Ad
    {
        $ad->update($attributes);

        return $ad->refresh();
    }
}
