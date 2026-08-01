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
        return [
            'ride_id' => Ride::factory(),
            'amount' => fake()->numberBetween(60, 600) * 50,
            'currency' => 'COP',
            'status' => PaymentStatus::Paid,
            'processed_at' => now(),
        ];
    }
}
