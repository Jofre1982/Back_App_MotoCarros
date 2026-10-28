<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mandados sin destino obligatorio y "Pedir motocarga" (pedido del dueño
 * probando la app): el destino pasa a ser opcional —texto libre en
 * `destination_text`, o el sitio del catálogo que ya existía— y
 * `vehicle_type` decide qué conductores ven el mandado. `motocarro` por
 * defecto, así los mandados que ya existían siguen igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('errands', function (Blueprint $table) {
            $table->string('vehicle_type', 20)->default('motocarro')->after('status');
            $table->string('destination_text', 255)->nullable()->after('destination_site_id');
            $table->unsignedBigInteger('destination_site_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('errands', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type', 'destination_text']);
            $table->unsignedBigInteger('destination_site_id')->nullable(false)->change();
        });
    }
};
