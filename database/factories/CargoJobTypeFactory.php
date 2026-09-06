<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CargoJobType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CargoJobType>
 */
class CargoJobTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'price' => fake()->numberBetween(10, 40) * 1000,
        ];
    }
}
