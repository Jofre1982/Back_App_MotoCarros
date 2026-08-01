<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverProfile>
 */
class DriverProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'license_number' => fake()->unique()->bothify('LIC-######'),
        ];
    }

    /**
     * Disponible para recibir solicitudes de viaje cercanas (historia #17),
     * con una ubicación conocida. Sin esto un conductor recién registrado
     * nunca entra en `NearbyDriverFinder` — mismo criterio del default en la
     * migración.
     */
    public function available(float $latitude = 4.710989, float $longitude = -74.072092): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => true,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_updated_at' => now(),
        ]);
    }
}
