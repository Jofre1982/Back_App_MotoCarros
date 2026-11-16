<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\ResetOperationalDataAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * Deja la base desde cero (solo quedan los administradores y la
 * configuración de destinos, precios y tipos de carga).
 *
 *   php artisan motoya:reiniciar-datos            (pregunta antes)
 *   php artisan motoya:reiniciar-datos --force    (sin preguntar)
 *
 * Sin terminal en el hosting se usa desde el Deploy de cPanel: el paso
 * `--si-hay-orden` solo hace algo si el dueño dejó el archivo vacío
 * `storage/app/REINICIAR_DATOS`. Ese archivo se borra antes de empezar, así
 * que un Deploy posterior nunca vuelve a borrar nada.
 */
final class ResetDataCommand extends Command
{
    public const string FLAG = 'app/REINICIAR_DATOS';

    public const string RESULT = 'app/REINICIAR_DATOS_resultado.txt';

    protected $signature = 'motoya:reiniciar-datos
                            {--force : No preguntar}
                            {--si-hay-orden : Solo si existe storage/app/REINICIAR_DATOS (para el Deploy)}';

    protected $description = 'Borra usuarios (menos administradores), viajes, mandados, conductores, documentos, fotos, favoritos y anuncios.';

    public function handle(ResetOperationalDataAction $reset): int
    {
        if ($this->option('si-hay-orden')) {
            $flag = storage_path(self::FLAG);
            if (! is_file($flag)) {
                $this->line('Sin orden de reinicio: no se borra nada.');

                return self::SUCCESS;
            }
            unlink($flag);
        } elseif (! $this->option('force') && ! $this->confirm('Esto BORRA todos los usuarios (menos administradores), viajes y fotos. ¿Seguro?')) {
            return self::FAILURE;
        }

        $deleted = $reset->handle();

        $summary = 'Reinicio de datos '.Date::now('America/Bogota')->format('Y-m-d H:i').' (hora Colombia). Borrado: '
            .collect($deleted)->map(fn (int $count, string $table) => "{$table}={$count}")->implode(', ');
        file_put_contents(storage_path(self::RESULT), $summary.PHP_EOL);
        Log::warning($summary);
        $this->info($summary);

        return self::SUCCESS;
    }
}
