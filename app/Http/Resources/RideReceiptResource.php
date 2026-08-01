<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el recibo de un viaje completado según el schema `RideReceipt` de
 * openapi.yaml (historia #26).
 *
 * A diferencia de `payment` embebido en `RideResource` —que solo publica
 * `status` porque el monto y la moneda ya están en el viaje que lo
 * contiene—, acá el recibo es el recurso principal de la respuesta, así que
 * sí repite `currency` y expone el desglose completo que produjo el cobro.
 *
 * @property-read Ride $resource
 */
class RideReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource->payment;

        return [
            'ride_id' => $this->resource->id,
            'currency' => $payment->currency,
            'base_fare' => $payment->base_fare,
            'distance_fare' => $payment->distance_fare,
            'time_fare' => $payment->time_fare,
            'waiting_fee' => $payment->waiting_fee,
            'subtotal' => $payment->subtotal,
            'minimum_applied' => $payment->minimum_applied,
            'total' => $payment->amount,
            'payment_status' => $payment->status->value,
            'completed_at' => $this->resource->completed_at?->toIso8601String(),
        ];
    }
}
