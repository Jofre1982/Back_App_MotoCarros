<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            // Único: la relación con el conductor es uno a uno. Es el índice, y
            // no la validación, lo que lo garantiza cuando dos requests del
            // mismo conductor llegan a la vez.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plate', 10)->unique();
            $table->string('model', 100);
            // Entero y no `year`: el tipo YEAR de MySQL no existe en SQLite (el
            // motor de desarrollo y de la suite) y solo cubre 1901-2155, un
            // rango que no aporta nada sobre la validación del Form Request.
            $table->unsignedSmallInteger('year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
