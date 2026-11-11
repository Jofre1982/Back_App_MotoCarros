<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Models\Ad;

/**
 * Suma un toque al botón de WhatsApp del anuncio.
 */
final class RecordAdClickAction
{
    public function handle(Ad $ad): void
    {
        $ad->increment('clicks');
    }
}
