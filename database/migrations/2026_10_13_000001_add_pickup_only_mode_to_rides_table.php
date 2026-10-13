<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Viaje "solo recogida" (historia #95).
 *
 * - `mode`: `catalog` para todos los viajes existentes, que no cambian.
 * - `destination_text`: a dónde va el pasajero, escrito por él; solo en
 *   `pickup_only` (ahí `destination_site_id` queda `null`, columna que ya era
 *   nullable desde la migración de sitios).
 * - `agreed_price`: el precio que registra el conductor al aceptar, mismo
 *   tipo que en `errands`.
 * - `estimated_fare` pasa a nullable: un viaje solo recogida no tiene
 *   estimado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->string('mode', 20)->default('catalog')->after('status');
            $table->string('destination_text', 255)->nullable()->after('destination_site_id');
            $table->unsignedInteger('agreed_price')->nullable()->after('estimated_fare');
            $table->unsignedInteger('estimated_fare')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['mode', 'destination_text', 'agreed_price']);
            $table->unsignedInteger('estimated_fare')->nullable(false)->change();
        });
    }
};
