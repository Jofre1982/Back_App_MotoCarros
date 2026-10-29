<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige los celulares guardados sin el +57 (p. ej. `+3108838480`, que
 * WhatsApp lee como de Holanda): les antepone el indicativo de Colombia. Si
 * ya existe otra cuenta con el número corregido, esa fila no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('phone', 'like', '+3%')
            ->orderBy('id')
            ->get(['id', 'phone'])
            ->each(function (object $row): void {
                if (preg_match('/^\+(3[0-9]{9})$/', (string) $row->phone, $match) !== 1) {
                    return;
                }
                $fixed = '+57'.$match[1];
                if (DB::table('users')->where('phone', $fixed)->exists()) {
                    return;
                }
                DB::table('users')->where('id', $row->id)->update(['phone' => $fixed]);
            });
    }

    public function down(): void
    {
        // Irreversible a propósito: no se sabe cuáles venían sin indicativo.
    }
};
