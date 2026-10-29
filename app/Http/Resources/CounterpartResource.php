<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;

/**
 * `counterpart` de viajes y mandados — ver openapi.yaml: la otra persona del
 * servicio (nombre y celular), para que pasajero y conductor se puedan
 * comunicar. Solo con el precio confirmado y el servicio activo.
 */
final class CounterpartResource
{
    /**
     * @return array{name: string, phone: string, avatar_url: string|null}|null
     */
    public static function for(?User $viewer, ?User $passenger, ?User $driver, bool $sharesContact): ?array
    {
        if (! $sharesContact || ! $viewer instanceof User || ! $passenger instanceof User || ! $driver instanceof User) {
            return null;
        }

        $other = match ($viewer->getKey()) {
            $passenger->getKey() => $driver,
            $driver->getKey() => $passenger,
            default => null,
        };

        return $other === null ? null : [
            'name' => $other->name,
            'phone' => $other->phone,
            'avatar_url' => AvatarUrl::forOthers($other),
        ];
    }
}
