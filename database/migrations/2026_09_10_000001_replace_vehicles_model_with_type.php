<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza `vehicles.model` (texto libre) por `vehicles.type`, respaldado
 * por `App\Enums\VehicleType` (historia técnica #75). El proyecto todavía no
 * está en producción, así que no hace falta un paso de backfill: se elimina
 * la columna vieja y se crea la nueva en la misma migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('model');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('type')->after('plate');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('model', 100)->after('plate');
        });
    }
};
