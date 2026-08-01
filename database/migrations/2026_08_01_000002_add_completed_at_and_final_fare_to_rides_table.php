<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // Cuándo el conductor marcó el viaje como completado (historia
            // #24). Mismo criterio que `started_at`: es un dato propio del
            // viaje, no algo deducible de `updated_at`, y nullable porque el
            // viaje puede terminar cancelado sin haber llegado a completarse.
            $table->timestamp('completed_at')->nullable();

            // El cobro final, recalculado al completar el viaje con el
            // trayecto realmente recorrido en vez del estimado al pedirlo
            // (ver `CalculateFareAction` y .claude/STANDARDS.md, "Cálculo de
            // tarifas"). Nullable por el mismo motivo que `completed_at`: no
            // hay tarifa final que registrar hasta que el viaje se completa.
            // Entero en la unidad mínima de `currency`, igual que
            // `estimated_fare`.
            $table->integer('final_fare')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'final_fare']);
        });
    }
};
