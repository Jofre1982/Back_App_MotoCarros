<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Cuántas cuentas hay por rol, para el resumen del panel de administrador
 * (historia #97). Sin filtros a propósito: el resumen no cambia mientras el
 * admin filtra o busca en la tabla (`ListUsersAction`).
 */
final readonly class CountUsersByRoleAction
{
    /**
     * @return array<string, int>
     */
    public function handle(): array
    {
        $counts = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $totals = [];
        foreach (UserRole::cases() as $role) {
            $totals[$role->value] = (int) ($counts[$role->value] ?? 0);
        }

        return $totals;
    }
}
