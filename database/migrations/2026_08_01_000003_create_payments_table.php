<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Un pago por viaje. `unique()` respalda a nivel de base la misma
            // regla que `ChargeRideAction` ya verifica antes de escribir: un
            // reintento duplicado (historia #25) no puede terminar en dos
            // filas, y entre la consulta y el INSERT caben dos llamadas
            // simultáneas, igual que `active_passenger_id` en `rides`. Se
            // borra con el viaje: un pago sin viaje no es nada que conciliar.
            $table->foreignId('ride_id')->unique()->constrained('rides')->cascadeOnDelete();

            // Entero en la unidad mínima de `currency`, nunca float: ver la
            // nota sobre dinero en .claude/STANDARDS.md. Es una copia del
            // `final_fare` del viaje en el momento del cobro, no una
            // referencia: si `final_fare` cambiara más adelante, el pago ya
            // procesado no debería moverse con él.
            $table->unsignedInteger('amount');
            $table->string('currency', 3);

            $table->string('status', 20)->index();

            // Cuándo el proveedor confirmó el cobro. Nullable porque un pago
            // `failed` no llegó a procesarse.
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
