<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta cuándo tiene el conductor acceso pagado o regalado. Primero lo
 * llena el admin al regalar meses (testers de la Play Store); después lo
 * actualizará la suscripción de Google Play.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->timestamp('subscription_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn('subscription_ends_at');
        });
    }
};
