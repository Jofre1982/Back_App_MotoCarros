<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();

            // `driver` (historia #27) o `passenger` (historia #28): a quién
            // califica esta fila, no quién la escribió. `unique()` junto con
            // `ride_id` respalda a nivel de base la misma regla que el Form
            // Request ya valida antes de escribir —una sola calificación por
            // dirección y viaje—, mismo criterio que `ride_id` en `payments`.
            $table->string('rated_role', 20);

            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(['ride_id', 'rated_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_ratings');
    }
};
