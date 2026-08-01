<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RatedRole;
use App\Models\Ride;
use App\Models\RideRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RideRating>
 */
class RideRatingFactory extends Factory
{
    /**
     * Por defecto la calificación del conductor (historia #27), que es la
     * única dirección que produce hoy algún flujo de la API.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ride_id' => Ride::factory(),
            'rated_role' => RatedRole::Driver,
            'score' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
