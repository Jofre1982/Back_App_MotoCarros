<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anuncios de negocios locales que ve el pasajero mientras espera su
 * motocarro. Los vende y los carga el dueño desde el panel; salen solo entre
 * `starts_on` y `ends_on` (fechas de Colombia) y si no están en pausa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('business_name', 60);
            $table->string('message', 140);
            $table->string('whatsapp', 20)->nullable();
            $table->string('image_path')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_paused')->default(false);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
