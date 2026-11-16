<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Deja la base "desde cero" para salir a producción: borra todas las
 * cuentas (menos las de administrador), viajes, mandados, conductores,
 * vehículos, documentos, fotos, favoritos y anuncios.
 *
 * Conserva la configuración que arma el admin en el panel: destinos y
 * precios (`sites`, `site_fares`) y tipos de carga (`cargo_job_types`).
 */
final class ResetOperationalDataAction
{
    /** Tablas que se vacían por completo (y sus ids vuelven a empezar en 1). */
    public const array TABLES = [
        'favorite_drivers',
        'ride_ratings',
        'payments',
        'rides',
        'errands',
        'driver_documents',
        'vehicles',
        'driver_profiles',
        'device_tokens',
        'phone_verification_codes',
        'password_reset_codes',
        'ads',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /** Carpetas de archivos subidos que se borran enteras. */
    public const array DIRECTORIES = ['driver-documents', 'errand-photos', 'ads'];

    /**
     * @return array<string, int> filas borradas por tabla (y `users`)
     */
    public function handle(): array
    {
        $deleted = [];

        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::TABLES as $table) {
                $deleted[$table] = DB::table($table)->count();
                DB::table($table)->truncate();
            }
            $deleted['users'] = User::query()->where('role', '!=', UserRole::Admin)->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $disk = Storage::disk('local');
        foreach (self::DIRECTORIES as $directory) {
            $disk->deleteDirectory($directory);
        }

        // Fotos de perfil: solo quedan las de los administradores.
        $keep = User::query()->whereNotNull('avatar_path')->pluck('avatar_path')->all();
        foreach ($disk->files('avatars') as $file) {
            if (! in_array($file, $keep, true)) {
                $disk->delete($file);
            }
        }

        return $deleted;
    }
}
