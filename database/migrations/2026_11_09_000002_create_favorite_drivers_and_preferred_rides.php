<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conductores favoritos (pedido del dueño: "hay gente que se casa con
 * ciertos conductores").
 *
 * - `favorite_drivers`: los conductores que cada pasajero guardó.
 * - `rides.preferred_driver_id` / `preferred_until`: el viaje se le ofrece
 *   solo a ese conductor hasta esa hora; después, a todos.
 * - `rides.opened_to_all_at`: cuándo se avisó a los demás conductores de
 *   que el favorito no respondió (para avisar una sola vez).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['passenger_id', 'driver_id']);
        });

        Schema::table('rides', function (Blueprint $table) {
            $table->foreignId('preferred_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('preferred_until')->nullable();
            $table->timestamp('opened_to_all_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_driver_id');
            $table->dropColumn(['preferred_until', 'opened_to_all_at']);
        });

        Schema::dropIfExists('favorite_drivers');
    }
};
