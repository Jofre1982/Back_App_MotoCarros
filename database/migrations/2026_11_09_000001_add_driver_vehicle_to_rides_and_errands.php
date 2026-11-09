<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Placa y tipo del vehículo con el que se hizo cada viaje o mandado, guardados
 * al aceptar (pedido del dueño: que el historial sirva ante una eventualidad
 * aunque el conductor cambie de vehículo después).
 *
 * Los servicios que ya tienen conductor se llenan con su vehículo actual: es
 * lo más cercano que hay a "el de ese día".
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['rides', 'errands'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('driver_vehicle_plate', 20)->nullable();
                $blueprint->string('driver_vehicle_type', 20)->nullable();
            });

            DB::table($table)->whereNotNull('driver_id')->update([
                'driver_vehicle_plate' => DB::raw("(SELECT plate FROM vehicles WHERE vehicles.user_id = {$table}.driver_id LIMIT 1)"),
                'driver_vehicle_type' => DB::raw("(SELECT type FROM vehicles WHERE vehicles.user_id = {$table}.driver_id LIMIT 1)"),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['rides', 'errands'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['driver_vehicle_plate', 'driver_vehicle_type']);
            });
        }
    }
};
