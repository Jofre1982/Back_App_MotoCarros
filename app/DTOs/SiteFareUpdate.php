<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PricingUnit;
use App\Enums\VehicleType;

/**
 * El precio de pasajero que el admin fija para un sitio y un tipo de
 * vehículo (historia técnica #85), ya validado.
 */
final readonly class SiteFareUpdate
{
    public function __construct(
        public VehicleType $vehicleType,
        public PricingUnit $pricingUnit,
        public int $dayPrice,
        public ?int $nightPrice,
    ) {}
}
