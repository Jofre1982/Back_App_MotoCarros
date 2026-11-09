<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Models\User;

/**
 * Guarda a un conductor como favorito del pasajero. Repetirlo no duplica.
 */
final readonly class AddFavoriteDriverAction
{
    public function handle(User $passenger, User $driver): User
    {
        $passenger->favoriteDrivers()->syncWithoutDetaching([$driver->getKey()]);

        return $driver->loadMissing('vehicle', 'driverProfile');
    }
}
