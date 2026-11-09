<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Models\User;

/**
 * Quita a un conductor de los favoritos del pasajero (si no estaba, no pasa
 * nada).
 */
final readonly class RemoveFavoriteDriverAction
{
    public function handle(User $passenger, int $driverId): void
    {
        $passenger->favoriteDrivers()->detach($driverId);
    }
}
