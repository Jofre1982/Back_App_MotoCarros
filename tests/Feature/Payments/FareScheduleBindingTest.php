<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Actions\Payments\CalculateFareAction;
use App\DTOs\FareSchedule;
use App\DTOs\RouteEstimate;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * El motor de tarifa se prueba aislado en tests/Unit; lo que se cubre acá es
 * el cableado: que los valores de config/fares.php lleguen al Action a través
 * del contenedor, y que una configuración imposible se detecte al resolverlo.
 */
class FareScheduleBindingTest extends TestCase
{
    public function test_builds_the_schedule_from_the_configured_values(): void
    {
        Config::set('fares.currency', 'COP');
        Config::set('fares.base', 2000);
        Config::set('fares.per_kilometer', 900);
        Config::set('fares.per_minute', 120);
        Config::set('fares.per_waiting_minute', 70);
        Config::set('fares.minimum', 3500);
        Config::set('fares.rounding_step', 100);

        $schedule = $this->app->make(FareSchedule::class);

        $this->assertSame('COP', $schedule->currency);
        $this->assertSame(2000, $schedule->base);
        $this->assertSame(900, $schedule->perKilometer);
        $this->assertSame(120, $schedule->perMinute);
        $this->assertSame(70, $schedule->perWaitingMinute);
        $this->assertSame(3500, $schedule->minimum);
        $this->assertSame(100, $schedule->roundingStep);
    }

    public function test_the_action_is_resolvable_with_its_schedule_injected(): void
    {
        Config::set('fares.base', 1500);
        Config::set('fares.per_kilometer', 800);
        Config::set('fares.per_minute', 100);
        Config::set('fares.minimum', 0);
        Config::set('fares.rounding_step', 50);

        $fare = $this->app->make(CalculateFareAction::class)
            ->handle(new RouteEstimate(distanceMeters: 5000, durationSeconds: 720));

        $this->assertSame(6700, $fare->total);
    }

    public function test_fails_loudly_when_the_configured_schedule_is_impossible(): void
    {
        Config::set('fares.rounding_step', 0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El paso de redondeo debe ser al menos 1');

        $this->app->make(FareSchedule::class);
    }

    /**
     * Los valores por defecto de config/fares.php tienen que ser un tarifario
     * coherente: es el que corre en cualquier entorno que no sobreescriba las
     * variables, incluido el primer arranque local.
     */
    public function test_the_shipped_defaults_are_a_valid_schedule(): void
    {
        $schedule = $this->app->make(FareSchedule::class);

        $this->assertGreaterThan(0, $schedule->minimum);
        $this->assertGreaterThan(0, $schedule->perKilometer);
        $this->assertGreaterThanOrEqual(1, $schedule->roundingStep);
        $this->assertSame(0, $schedule->minimum % $schedule->roundingStep, 'El mínimo debería ser un múltiplo del paso de redondeo.');
    }
}
