<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payments;

use App\Actions\Payments\CalculateFareAction;
use App\DTOs\FareSchedule;
use App\DTOs\RouteEstimate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * El motor de tarifa es una función pura de sus parámetros, así que estos
 * tests fijan un tarifario propio y no leen config: si el negocio cambia el
 * precio del kilómetro mañana, estos tests no tienen por qué ponerse rojos.
 */
class CalculateFareActionTest extends TestCase
{
    /**
     * Tarifario de prueba con números redondos para que las cuentas de cada
     * test se puedan verificar a mano.
     */
    private function schedule(int $minimum = 3000, int $roundingStep = 50): FareSchedule
    {
        return new FareSchedule(
            currency: 'COP',
            base: 1500,
            perKilometer: 800,
            perMinute: 100,
            perWaitingMinute: 60,
            minimum: $minimum,
            roundingStep: $roundingStep,
        );
    }

    public function test_charges_base_distance_and_time_on_a_normal_ride(): void
    {
        // 5 km → 4000; 12 min → 1200; + base 1500 = 6700, ya múltiplo de 50.
        $fare = (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 5000, durationSeconds: 720));

        $this->assertSame(1500, $fare->base);
        $this->assertSame(4000, $fare->distance);
        $this->assertSame(1200, $fare->time);
        $this->assertSame(0, $fare->waiting);
        $this->assertSame(6700, $fare->subtotal);
        $this->assertSame(6700, $fare->total);
        $this->assertFalse($fare->minimumApplied);
        $this->assertSame('COP', $fare->currency);
    }

    public function test_applies_the_minimum_fare_on_a_very_short_ride(): void
    {
        // 400 m → 320; 90 s → 150; + base 1500 = 1970, por debajo del mínimo.
        $fare = (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 400, durationSeconds: 90));

        $this->assertSame(1970, $fare->subtotal);
        $this->assertSame(3000, $fare->total);
        $this->assertTrue($fare->minimumApplied);
    }

    public function test_does_not_apply_the_minimum_when_the_subtotal_already_reaches_it(): void
    {
        // 1 km → 800; 420 s → 700; + base 1500 = 3000, exactamente el mínimo.
        $fare = (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 1000, durationSeconds: 420));

        $this->assertSame(3000, $fare->subtotal);
        $this->assertSame(3000, $fare->total);
        $this->assertFalse($fare->minimumApplied);
    }

    public function test_scales_with_distance_on_a_long_ride(): void
    {
        // 42.5 km → 34000; 65 min → 6500; + base 1500 = 42000.
        $fare = (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 42500, durationSeconds: 3900));

        $this->assertSame(34000, $fare->distance);
        $this->assertSame(6500, $fare->time);
        $this->assertSame(42000, $fare->subtotal);
        $this->assertSame(42000, $fare->total);
        $this->assertFalse($fare->minimumApplied);
    }

    public function test_charges_the_requested_waiting_time_on_top_of_the_ride(): void
    {
        // 10 min de espera → 600, sobre el mismo viaje del primer test.
        $fare = (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 5000, durationSeconds: 720), waitingSeconds: 600);

        $this->assertSame(600, $fare->waiting);
        $this->assertSame(7300, $fare->subtotal);
        $this->assertSame(7300, $fare->total);
    }

    public function test_waiting_time_defaults_to_zero(): void
    {
        $route = new RouteEstimate(distanceMeters: 5000, durationSeconds: 720);
        $action = new CalculateFareAction($this->schedule());

        $this->assertEquals($action->handle($route, 0), $action->handle($route));
    }

    public function test_waiting_time_can_lift_a_short_ride_above_the_minimum(): void
    {
        // El viaje corto del test del mínimo (1970) + 30 min de espera (1800).
        $fare = (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 400, durationSeconds: 90), waitingSeconds: 1800);

        $this->assertSame(3770, $fare->subtotal);
        $this->assertSame(3800, $fare->total);
        $this->assertFalse($fare->minimumApplied);
    }

    public function test_prorates_fractions_of_a_kilometer_and_of_a_minute(): void
    {
        // 1500 m → 1200 (km y medio, no dos km); 30 s → 50 (medio minuto).
        $fare = (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 1500, durationSeconds: 30));

        $this->assertSame(1200, $fare->distance);
        $this->assertSame(50, $fare->time);
    }

    public function test_rounds_the_total_up_to_the_configured_step(): void
    {
        // 1234 m → 987; 61 s → 102; + base 1500 = 2589 → 2600.
        $fare = (new CalculateFareAction($this->schedule(minimum: 0)))
            ->handle(new RouteEstimate(distanceMeters: 1234, durationSeconds: 61));

        $this->assertSame(2589, $fare->subtotal);
        $this->assertSame(2600, $fare->total);
    }

    public function test_leaves_the_total_untouched_when_it_already_lands_on_the_step(): void
    {
        // 1250 m → 1000; 300 s → 500; + base 1500 = 3000.
        $fare = (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 1250, durationSeconds: 300));

        $this->assertSame(3000, $fare->total);
    }

    public function test_a_rounding_step_of_one_disables_the_rounding(): void
    {
        $fare = (new CalculateFareAction($this->schedule(minimum: 0, roundingStep: 1)))
            ->handle(new RouteEstimate(distanceMeters: 1234, durationSeconds: 61));

        $this->assertSame(2589, $fare->total);
    }

    public function test_a_zero_length_route_still_costs_the_minimum(): void
    {
        $fare = (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 0, durationSeconds: 0));

        $this->assertSame(1500, $fare->subtotal);
        $this->assertSame(3000, $fare->total);
        $this->assertTrue($fare->minimumApplied);
    }

    public function test_rejects_a_negative_distance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('la distancia del trayecto');

        (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: -1, durationSeconds: 60));
    }

    public function test_rejects_a_negative_duration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('la duración del trayecto');

        (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 1000, durationSeconds: -1));
    }

    public function test_rejects_a_negative_waiting_time(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('el tiempo de espera');

        (new CalculateFareAction($this->schedule()))
            ->handle(new RouteEstimate(distanceMeters: 1000, durationSeconds: 60), waitingSeconds: -1);
    }
}
