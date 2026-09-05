<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Único sin importar la cuenta: el mismo dispositivo físico puede
            // volver a registrarse bajo otra cuenta (se cerró sesión y entró
            // otra persona), y ahí el token tiene que mudarse de dueño, no
            // duplicarse (ver RegisterDeviceTokenAction).
            $table->string('token')->unique();
            $table->string('platform');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
