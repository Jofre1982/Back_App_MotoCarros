<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            // Si el conductor puede recibir solicitudes de viaje cercanas
            // (historia #17). Por defecto no: recién registrado, ningún
            // conductor está trabajando todavía.
            $table->boolean('is_available')->default(false)->after('license_number');

            // Última posición conocida mientras está disponible. Nullable: un
            // conductor puede marcarse disponible sin haber compartido
            // ubicación todavía, aunque entonces no entra en ninguna búsqueda
            // por radio (ver EloquentNearbyDriverFinder). Misma precisión que
            // las coordenadas de `rides` (ver esa migración).
            $table->decimal('latitude', 9, 7)->nullable()->after('is_available');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            // Cuándo se actualizó la posición por última vez. Separado de
            // `updated_at`: esa columna también cambia al editar solo
            // `is_available` o `license_number`, y no serviría para decidir
            // qué tan fresca es la ubicación.
            $table->timestamp('location_updated_at')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_available', 'latitude', 'longitude', 'location_updated_at']);
        });
    }
};
