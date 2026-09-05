<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\DevicePlatform;

/**
 * El token de push que un dispositivo registra, ya validado.
 *
 * No lleva la cuenta dueña, mismo criterio que `VehicleRegistration`: de
 * quién es el token lo decide quien invoca la Action —el guard, en el caso
 * del endpoint— y nunca la entrada.
 */
final readonly class DeviceTokenRegistration
{
    public function __construct(
        public string $token,
        public DevicePlatform $platform,
    ) {}
}
