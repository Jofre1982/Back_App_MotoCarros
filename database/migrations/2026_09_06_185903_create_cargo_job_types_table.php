<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_job_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // Entero en COP (sin subunidad), nunca float — ver
            // .claude/STANDARDS.md ("Dinero: enteros, nunca float").
            $table->unsignedInteger('price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_job_types');
    }
};
