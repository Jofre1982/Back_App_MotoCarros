<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ad;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Ad>
 */
class AdFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $today = Date::now(Ad::TIMEZONE)->toDateString();

        return [
            'business_name' => fake()->company(),
            'message' => '2x1 en hamburguesas los viernes',
            'whatsapp' => '3001234567',
            'starts_on' => $today,
            'ends_on' => Date::parse($today)->addDays(30)->toDateString(),
            'is_paused' => false,
        ];
    }
}
