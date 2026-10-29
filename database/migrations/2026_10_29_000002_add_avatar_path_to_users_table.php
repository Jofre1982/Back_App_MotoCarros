<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto de perfil (pasajero o conductor) para que la otra persona del
 * servicio la reconozca. La de un conductor la aprueba un admin. Ruta en el disco `local` (privado), no URL pública.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('phone_verified_at');
            // `pending` (conductor, hasta que un admin la revise),
            // `approved` o `rejected`; `null` sin foto.
            $table->string('avatar_status', 20)->nullable()->after('avatar_path');
            $table->string('avatar_rejection_reason')->nullable()->after('avatar_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'avatar_status', 'avatar_rejection_reason']);
        });
    }
};
