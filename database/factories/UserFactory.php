<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Por defecto un pasajero: es el rol con el que se registra la mayoría y
     * el único que no necesita nada más para existir. Para un conductor está
     * el estado `driver()`, y su perfil lo crea DriverProfileFactory.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('+57300#######'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Passenger,
        ];
    }

    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Driver,
        ]);
    }
}
