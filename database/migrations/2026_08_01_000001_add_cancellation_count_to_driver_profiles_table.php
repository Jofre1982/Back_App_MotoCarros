<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            // Cuántas veces el conductor devolvió al pool un viaje que ya
            // había aceptado (historia #23), sin distinguir el motivo. Es un
            // conteo acumulado para uso futuro en políticas de calidad — esta
            // historia solo lo registra, no lo usa para bloquear ni penalizar
            // todavía.
            $table->unsignedInteger('cancellation_count')->default(0)->after('license_number');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn('cancellation_count');
        });
    }
};
