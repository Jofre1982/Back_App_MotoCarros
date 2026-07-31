<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\FareSchedule;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FareScheduleTest extends TestCase
{
    private static function make(
        string $currency = 'COP',
        int $base = 1500,
        int $perKilometer = 800,
        int $perMinute = 100,
        int $perWaitingMinute = 60,
        int $minimum = 3000,
        int $roundingStep = 50,
    ): FareSchedule {
        return new FareSchedule(
            currency: $currency,
            base: $base,
            perKilometer: $perKilometer,
            perMinute: $perMinute,
            perWaitingMinute: $perWaitingMinute,
            minimum: $minimum,
            roundingStep: $roundingStep,
        );
    }

    public function test_accepts_a_valid_schedule(): void
    {
        $schedule = self::make();

        $this->assertSame('COP', $schedule->currency);
        $this->assertSame(1500, $schedule->base);
        $this->assertSame(800, $schedule->perKilometer);
        $this->assertSame(50, $schedule->roundingStep);
    }

    /**
     * Una tarifa en cero es una decisión de negocio válida (una promoción sin
     * cargo base, por ejemplo); lo que no puede es ser negativa.
     */
    public function test_accepts_rates_of_zero(): void
    {
        $schedule = self::make(base: 0, perMinute: 0, minimum: 0);

        $this->assertSame(0, $schedule->base);
        $this->assertSame(0, $schedule->perMinute);
        $this->assertSame(0, $schedule->minimum);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCurrencies(): array
    {
        return [
            'minúsculas' => ['cop'],
            'demasiado corta' => ['CO'],
            'demasiado larga' => ['COPS'],
            'con símbolo' => ['CO$'],
            'vacía' => [''],
        ];
    }

    #[DataProvider('invalidCurrencies')]
    public function test_rejects_an_invalid_currency(string $currency): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Moneda inválida');

        self::make(currency: $currency);
    }

    /**
     * Cada caso pone en -1 una sola tarifa y deja el resto válida, para
     * comprobar que el mensaje señala exactamente la clave de configuración
     * que hay que corregir.
     *
     * @return array<string, array{string, int, int, int, int, int}>
     */
    public static function negativeRates(): array
    {
        return [
            'base' => ['base', -1, 800, 100, 60, 3000],
            'por kilómetro' => ['per_kilometer', 1500, -1, 100, 60, 3000],
            'por minuto' => ['per_minute', 1500, 800, -1, 60, 3000],
            'por minuto de espera' => ['per_waiting_minute', 1500, 800, 100, -1, 3000],
            'mínimo' => ['minimum', 1500, 800, 100, 60, -1],
        ];
    }

    #[DataProvider('negativeRates')]
    public function test_rejects_a_negative_rate(
        string $configKey,
        int $base,
        int $perKilometer,
        int $perMinute,
        int $perWaitingMinute,
        int $minimum,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("La tarifa '{$configKey}' no puede ser negativa");

        self::make(
            base: $base,
            perKilometer: $perKilometer,
            perMinute: $perMinute,
            perWaitingMinute: $perWaitingMinute,
            minimum: $minimum,
        );
    }

    public function test_rejects_a_rounding_step_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El paso de redondeo debe ser al menos 1');

        self::make(roundingStep: 0);
    }
}
