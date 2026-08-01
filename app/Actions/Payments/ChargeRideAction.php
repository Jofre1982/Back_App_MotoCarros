<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentProcessingFailed;
use App\Models\Payment;
use App\Models\Ride;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Procesa el cobro de un viaje recién completado (historia #25).
 *
 * La invoca `CompleteRideAction` justo después de persistir `final_fare`: el
 * monto a cobrar sale de ahí, no se recalcula acá. No conoce HTTP ni cómo se
 * llegó al viaje, mismo criterio que el resto de las Actions.
 *
 * Un fallo del proveedor de pago (`PaymentProcessingFailed`) no se deja
 * propagar: se atrapa acá y el pago queda `failed`. Que el cobro no se pueda
 * procesar es un problema del pago, no del viaje —el conductor ya hizo el
 * trayecto—, así que no puede impedir que el viaje quede `completed`.
 */
final readonly class ChargeRideAction
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * Idempotente: si el viaje ya tiene un pago (reintento duplicado), lo
     * devuelve tal cual en vez de procesar un segundo cobro. El índice único
     * de `ride_id` en la tabla `payments` respalda esto mismo si dos
     * llamadas llegaran a la vez.
     */
    public function handle(Ride $ride): Payment
    {
        $existing = $ride->payment;

        if ($existing !== null) {
            return $existing;
        }

        [$status, $processedAt] = $this->process($ride);

        $payment = Payment::create([
            'ride_id' => $ride->id,
            'amount' => $ride->final_fare,
            'currency' => $ride->currency,
            'status' => $status,
            'processed_at' => $processedAt,
        ]);

        // `$ride->payment` de la línea de arriba ya dejó la relación
        // cacheada en `null` sobre esta misma instancia; sin esto, quien
        // siga usando este `$ride` (p. ej. `CompleteRideController` armando
        // la respuesta) seguiría viendo `payment: null` pese a que la fila
        // ya existe.
        $ride->setRelation('payment', $payment);

        return $payment;
    }

    /**
     * @return array{0: PaymentStatus, 1: Carbon|null}
     */
    private function process(Ride $ride): array
    {
        try {
            $this->gateway->charge($ride);
        } catch (PaymentProcessingFailed $e) {
            Log::error('No se pudo procesar el cobro del viaje.', [
                'ride_id' => $ride->id,
                'reason' => $e->getMessage(),
            ]);

            return [PaymentStatus::Failed, null];
        }

        return [PaymentStatus::Paid, now()];
    }
}
