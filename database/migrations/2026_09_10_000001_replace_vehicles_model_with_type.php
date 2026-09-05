<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza `vehicles.model` (texto libre) por `vehicles.type`, respaldado
 * por `App\Enums\VehicleType` (historia técnica #75). El proyecto todavía no
 * está en producción, así que no hace falta un paso de backfill real: se
 * elimina la columna vieja y se crea la nueva en la misma migración.
 *
 * `type` lleva un `default()` transitorio (`motocarro`) únicamente porque
 * SQLite no permite agregar una columna NOT NULL sin valor por defecto
 * cuando la tabla ya tiene filas — ni siquiera dentro de una migración que
 * nunca corrió antes contra datos reales, cualquier entorno de desarrollo
 * con vehículos de prueba ya cargados choca con esto. Ninguna fila nueva
 * depende de ese valor: el Form Request exige `type` como campo requerido,
 * así que el default nunca lo pone un cliente real, solo lo necesita el
 * ALTER TABLE para poder ejecutarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('model');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('type')->default('motocarro')->after('plate');
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
