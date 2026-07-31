<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registra los canales de broadcasting de la aplicación.
 *
 * Carga routes/channels.php a mano en vez de usar `withBroadcasting()` en
 * bootstrap/app.php porque ese helper, además de los canales, registra la ruta
 * de autorización con GET y POST y fuera de cualquier convención de esta API.
 * Acá la ruta se declara como una más en routes/api.php: versionada bajo
 * /api/v1, solo POST y documentada en openapi.yaml como el resto (ver
 * .claude/STANDARDS.md).
 */
final class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        require base_path('routes/channels.php');
    }
}
