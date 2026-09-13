<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Errand>
 */
class ErrandFactory extends Factory
{
    /**
     * Por defecto un mandado recién solicitado, sin conductor ni precio: es
     * el único estado que nace de `POST /errands` (historia #92). El origen
     * cae alrededor de Bogotá, mismo criterio que `RideFactory`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'passenger_id' => User::factory(),
            'driver_id' => null,
            'status' => ErrandStatus::Requested,
            'description' => fake()->sentence(),
            'photo_path' => null,
            'origin_latitude' => fake()->randomFloat(6, 4.60, 4.78),
            'origin_longitude' => fake()->randomFloat(6, -74.15, -74.03),
            'destination_site_id' => Site::factory(),
            'agreed_price' => null,
            'completed_at' => null,
        ];
    }
}
