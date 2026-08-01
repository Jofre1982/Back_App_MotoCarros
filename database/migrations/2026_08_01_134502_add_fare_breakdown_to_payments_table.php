<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // El desglose que produjo `amount` (historia #26): la misma
            // `FareBreakdown` que ya calculaba `CompleteRideAction` para
            // `final_fare`, pero hasta ahora se descartaba después de sumarla.
            // Sin esto, el recibo tendría que recalcularla al leerla, y un
            // cambio posterior en config/fares.php haría que un viaje viejo
            // mostrara un desglose que nunca fue el que se cobró — mismo
            // motivo por el que `estimated_fare` y `final_fare` se guardan en
            // vez de recalcularse (ver rides).
            $table->unsignedInteger('base_fare')->after('currency');
            $table->unsignedInteger('distance_fare')->after('base_fare');
            $table->unsignedInteger('time_fare')->after('distance_fare');
            $table->unsignedInteger('waiting_fee')->after('time_fare');
            $table->unsignedInteger('subtotal')->after('waiting_fee');

            // `true` cuando el subtotal no llegaba al mínimo configurado y el
            // cobro lo determinó ese piso en vez de la suma de conceptos
            // (ver `FareBreakdown::$minimumApplied`).
            $table->boolean('minimum_applied')->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'base_fare',
                'distance_fare',
                'time_fare',
                'waiting_fee',
                'subtotal',
                'minimum_applied',
            ]);
        });
    }
};
