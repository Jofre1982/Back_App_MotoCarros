<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Models\Ad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Cambia la imagen de un anuncio y borra la anterior.
 */
final class ReplaceAdImageAction
{
    public function handle(Ad $ad, UploadedFile $image): Ad
    {
        $previous = $ad->image_path;
        $ad->update(['image_path' => $image->store('ads', 'local')]);

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        return $ad->refresh();
    }
}
