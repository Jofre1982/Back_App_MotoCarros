<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Models\Ad;
use Illuminate\Http\UploadedFile;

/**
 * Crea un anuncio. La imagen va al disco privado: la sirve
 * GET /ads/{ad}/image solo a cuentas autenticadas.
 */
final class CreateAdAction
{
    /**
     * @param  array{business_name: string, message: string, whatsapp: string|null, starts_on: string, ends_on: string}  $attributes
     */
    public function handle(array $attributes, ?UploadedFile $image): Ad
    {
        return Ad::create([
            ...$attributes,
            'image_path' => $image?->store('ads', 'local'),
        ]);
    }
}
