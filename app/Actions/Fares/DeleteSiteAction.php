<?php

declare(strict_types=1);

namespace App\Actions\Fares;

use App\Models\Site;

/**
 * Borra un sitio del catálogo (historia técnica #85). Sus precios se van
 * con él por `cascadeOnDelete` en `site_fares.site_id`: no puede quedar un
 * precio huérfano apuntando a un sitio que ya no existe.
 */
final class DeleteSiteAction
{
    public function handle(Site $site): void
    {
        $site->delete();
    }
}
