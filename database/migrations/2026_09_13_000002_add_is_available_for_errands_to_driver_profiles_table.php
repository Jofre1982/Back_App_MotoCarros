<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            // Pool de conductores separado del de viajes (historia #92): un
            // conductor puede estar disponible para mandados, para viajes,
            // ambos o ninguno — independiente de `is_available`. Por
            // defecto no, mismo criterio que esa columna: recién registrado,
            // ningún conductor está trabajando todavía.
            $table->boolean('is_available_for_errands')->default(false)->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn('is_available_for_errands');
        });
    }
};
