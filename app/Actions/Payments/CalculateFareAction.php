<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\DTOs\FareBreakdown;
use App\DTOs\FareSchedule;
use App\DTOs\RouteEstimate;
use InvalidArgumentException;

/**
 * Calcula lo que cuesta un viaje a partir de su distancia y duración.
 *
 * Es la única fuente de verdad de la tarifa: la estimación que se le muestra
 * al pasajero antes de pedir el viaje y el cobro al terminarlo salen de acá
 * con los mismos parámetros, y lo único que cambia entre ambos momentos es el
 * `RouteEstimate` que se le pasa (estimado del proveedor de mapas primero,
 * trayecto realmente recorrido después). Si cada lado tuviera su propia
 * fórmula, la diferencia entre lo prometido y lo cobrado sería un bug
 * invisible hasta que el pasajero reclama.
 *
 * No conoce HTTP ni el modelo `Ride`: recibe el trayecto y la espera, y
 * devuelve el desglose. Quien la invoque —controller, job o comando— se
 * encarga de traducir eso a su propio formato.
 */
final readonly class CalculateFareAction
{
    private const SECONDS_PER_MINUTE = 60;

    private const METERS_PER_KILOMETER = 1000;

    public function __construct(private FareSchedule $schedule) {}

    /**
     * @param  RouteEstimate  $route  Distancia y duración del trayecto, estimadas o reales.
     * @param  int  $waitingSeconds  Espera solicitada por el pasajero con el viaje ya aceptado,
     *                               fuera de la duración del trayecto.
     *
     * @throws InvalidArgumentException si el trayecto o la espera traen valores negativos.
     */
    public function handle(RouteEstimate $route, int $waitingSeconds = 0): FareBreakdown
    {
        $this->assertNotNegative('la distancia del trayecto', $route->distanceMeters);
        $this->assertNotNegative('la duración del trayecto', $route->durationSeconds);
        $this->assertNotNegative('el tiempo de espera', $waitingSeconds);

        $distance = $this->prorate($route->distanceMeters, $this->schedule->perKilometer, self::METERS_PER_KILOMETER);
        $time = $this->prorate($route->durationSeconds, $this->schedule->perMinute, self::SECONDS_PER_MINUTE);
        $waiting = $this->prorate($waitingSeconds, $this->schedule->perWaitingMinute, self::SECONDS_PER_MINUTE);

        $subtotal = $this->schedule->base + $distance + $time + $waiting;
        $minimumApplied = $subtotal < $this->schedule->minimum;

        return new FareBreakdown(
            currency: $this->schedule->currency,
            base: $this->schedule->base,
            distance: $distance,
            time: $time,
            waiting: $waiting,
            subtotal: $subtotal,
            total: $this->roundUpToStep(max($subtotal, $this->schedule->minimum)),
            minimumApplied: $minimumApplied,
        );
    }

    /**
     * Cobra `$rate` por cada `$unit` de `$quantity`, prorrateando las
     * fracciones en vez de cobrar la unidad completa: 1500 m pagan kilómetro y
     * medio, no dos.
     */
    private function prorate(int $quantity, int $rate, int $unit): int
    {
        return (int) round($quantity * $rate / $unit);
    }

    /**
     * Redondea hacia arriba, nunca hacia el múltiplo más cercano: el mínimo es
     * un piso y el redondeo no puede perforarlo.
     */
    private function roundUpToStep(int $amount): int
    {
        $step = $this->schedule->roundingStep;

        return intdiv($amount + $step - 1, $step) * $step;
    }

    private function assertNotNegative(string $name, int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("Valor negativo no admitido en {$name}: {$value}.");
        }
    }
}
