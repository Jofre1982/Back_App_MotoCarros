<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('path');
            $table->string('status')->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Un documento vigente por tipo y conductor: volver a subir el
            // mismo tipo reemplaza la fila existente (ver
            // UploadDriverDocumentAction), nunca crea un duplicado.
            $table->unique(['driver_profile_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
    }
};
