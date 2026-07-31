<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            // Formato de placa de moto colombiana (tres letras, dos dígitos,
            // una letra). La API no lo exige —el `regex` del Form Request es
            // más laxo a propósito, ver openapi.yaml— pero para los datos de
            // prueba conviene que se parezcan a los reales.
            'plate' => fake()->unique()->bothify('???##?', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'),
            'model' => fake()->randomElement([
                'Bajaj Boxer CT 100',
                'Yamaha YBR 125',
                'Honda CB 110',
                'Suzuki GN 125',
            ]),
            'year' => fake()->numberBetween(2010, (int) date('Y')),
        ];
    }
}
