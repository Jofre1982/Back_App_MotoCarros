<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El pasajero acepta o rechaza el precio que propone el conductor (pedido
 * del dueño probando la app). `price_confirmed_at` marca cuándo lo aceptó.
 *
 * Los viajes y mandados que ya tenían conductor quedan como confirmados:
 * se aceptaron con la regla de antes y no tienen que quedar trabados.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['rides', 'errands'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('price_confirmed_at')->nullable()->after('agreed_price');
            });

            DB::table($table)
                ->whereNotNull('driver_id')
                ->update(['price_confirmed_at' => DB::raw('updated_at')]);
        }
    }

    public function down(): void
    {
        foreach (['rides', 'errands'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('price_confirmed_at');
            });
        }
    }
};
