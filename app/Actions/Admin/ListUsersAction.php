<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista las cuentas registradas para el panel de administrador (historia
 * #97): de la más reciente a la más antigua, paginadas, con filtro opcional
 * por rol y búsqueda parcial por nombre, email o teléfono.
 */
final readonly class ListUsersAction
{
    public const PER_PAGE = 50;

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(?UserRole $role, ?string $search): LengthAwarePaginator
    {
        return User::query()
            ->with('driverProfile')
            ->when($role !== null, fn (Builder $query) => $query->where('role', $role))
            ->when($search !== null, fn (Builder $query) => $this->applySearch($query, (string) $search))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        // `%` y `_` del texto buscado se escapan: son comodines de LIKE y el
        // admin los escribe como texto literal (p.ej. un email con `_`).
        $pattern = '%'.addcslashes($search, '%_\\').'%';

        $query->where(function (Builder $inner) use ($pattern): void {
            $inner->where('name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern)
                ->orWhere('phone', 'like', $pattern);
        });
    }
}
