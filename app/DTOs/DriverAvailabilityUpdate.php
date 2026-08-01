<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Entrada de `UpdateDriverAvailabilityAction` (historia #17).
 *
 * `location` es opcional y no un `Coordinates` a secas: marcarse no
 * disponible no necesita traer una posición, y forzarla obligaría al
 * cliente a inventar una.
 */
final readonly class DriverAvailabilityUpdate
{
    public function __construct(
        public bool $isAvailable,
        public ?Coordinates $location,
    ) {}
}
