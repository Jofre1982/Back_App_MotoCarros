<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Ride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Por defecto un cobro ya pagado, asociado a un viaje nuevo: es el
     * resultado normal de `ChargeRideAction` con el `PaymentGateway`
     * configurado hoy (`NullPaymentGateway`, ver AppServiceProvider).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = 1500;
        $distance = fake()->numberBetween(10, 200) * 50;
        $time = fake()->numberBetween(5, 100) * 10;
        $waiting = 0;
        $subtotal = $base + $distance + $time + $waiting;

        return [
            'ride_id' => Ride::factory(),
            'amount' => $subtotal,
            'currency' => 'COP',
            'base_fare' => $base,
            'distance_fare' => $distance,
            'time_fare' => $time,
            'waiting_fee' => $waiting,
            'subtotal' => $subtotal,
            'minimum_applied' => false,
            'status' => PaymentStatus::Paid,
            'processed_at' => now(),
        ];
    }
}
