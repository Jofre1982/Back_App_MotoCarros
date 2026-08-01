<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // Cuándo el conductor recogió al pasajero (historia #19). Es un
            // dato propio del viaje y no algo que se pueda deducir de
            // `updated_at`: esa columna se mueve con cualquier escritura
            // posterior —completar el viaje, cancelarlo— y para el pasajero,
            // para el recibo (#26) y para la tarifa final (#24) la hora de
            // inicio tiene que quedar fija.
            //
            // Nullable porque el viaje existe mucho antes de empezar: nace
            // `requested` y puede terminar cancelado sin haber arrancado
            // nunca. NULL significa exactamente eso, "todavía no empezó", y no
            // hay valor por defecto que pueda representarlo sin mentir.
            //
            // No lleva `after()`: SQLite (desarrollo y tests) no reordena
            // columnas, así que la posición física dependería del motor.
            $table->timestamp('started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
