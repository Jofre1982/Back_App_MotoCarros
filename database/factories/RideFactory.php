<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RideMode;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ride>
 */
class RideFactory extends Factory
{
    /**
     * Por defecto un viaje recién solicitado y sin conductor: es el único
     * estado que esta API sabe crear hoy (historia #15), y el punto de partida
     * del resto del ciclo de vida.
     *
     * El origen cae alrededor de Bogotá para que los datos de prueba se
     * parezcan a los reales; nada del dominio depende de la ciudad. El
     * destino es un sitio del catálogo (historia #87), no coordenadas.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'passenger_id' => User::factory(),
            'driver_id' => null,
            'status' => RideStatus::Requested,
            'origin_latitude' => fake()->randomFloat(6, 4.60, 4.78),
            'origin_longitude' => fake()->randomFloat(6, -74.15, -74.03),
            'destination_site_id' => Site::factory(),
            'passenger_count' => fake()->numberBetween(1, 3),
            'currency' => 'COP',
            // Múltiplo del paso de redondeo, igual que cualquier monto que
            // salga del motor de tarifa (ver config/fares.php).
            'estimated_fare' => fake()->numberBetween(60, 600) * 50,
        ];
    }

    /**
     * Viaje "solo recogida" (historia #95): sin sitio ni estimado, con el
     * destino escrito por el pasajero.
     */
    public function pickupOnly(): static
    {
        return $this->state(fn (): array => [
            'mode' => RideMode::PickupOnly,
            'destination_site_id' => null,
            'destination_text' => fake()->randomElement([
                'Al hospital',
                'Casa de mi mamá, barrio La Esperanza',
                'El puerto, frente a la lancha',
            ]),
            'estimated_fare' => null,
            'agreed_price' => null,
        ]);
    }
}
