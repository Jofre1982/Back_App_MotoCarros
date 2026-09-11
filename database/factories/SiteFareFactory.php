<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PricingUnit;
use App\Enums\VehicleType;
use App\Models\Site;
use App\Models\SiteFare;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteFare>
 */
class SiteFareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'vehicle_type' => fake()->randomElement(VehicleType::cases()),
            'pricing_unit' => fake()->randomElement(PricingUnit::cases()),
            'day_price' => fake()->numberBetween(4, 20) * 1000,
            'night_price' => null,
        ];
    }
}
