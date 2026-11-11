<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Models\Ad;
use Illuminate\Support\Facades\Storage;

/**
 * Borra un anuncio y su imagen.
 */
final class DeleteAdAction
{
    public function handle(Ad $ad): void
    {
        if ($ad->image_path !== null) {
            Storage::disk('local')->delete($ad->image_path);
        }

        $ad->delete();
    }
}
