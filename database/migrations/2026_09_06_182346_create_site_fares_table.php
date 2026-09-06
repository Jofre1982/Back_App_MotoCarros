<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_fares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_type', 20);
            $table->string('pricing_unit', 20);
            // Enteros en COP (sin subunidad), nunca float — ver
            // .claude/STANDARDS.md ("Dinero: enteros, nunca float").
            $table->unsignedInteger('day_price');
            // Recargo nocturno (desde las 10pm), opcional: hoy solo lo tiene
            // el sitio "Casco urbano". NULL significa que ese sitio cobra lo
            // mismo de dia y de noche.
            $table->unsignedInteger('night_price')->nullable();
            $table->timestamps();

            // Un sitio tiene a lo sumo un precio por tipo de vehiculo.
            $table->unique(['site_id', 'vehicle_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_fares');
    }
};
