<?php

declare(strict_types=1);

namespace App\Actions\Fares;

use App\Models\Site;

/**
 * Crea un sitio nuevo en el catálogo (historia técnica #85). Nace sin
 * precio: el admin lo fija aparte con `SetSiteFareAction`, porque un sitio
 * puede necesitar precio de Motocarro, de Motocarga, o los dos, y no
 * necesariamente al mismo tiempo.
 */
final class CreateSiteAction
{
    public function handle(string $name): Site
    {
        return Site::create(['name' => $name]);
    }
}
