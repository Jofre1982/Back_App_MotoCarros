<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Actions\Payments\ChargeRideAction;
use App\DTOs\FareBreakdown;
use App\Enums\RideStatus;
use App\Events\Realtime\RideStatusChanged;
use App\Models\Ride;

/**
 * Marca como completado un viaje que el conductor asignado tiene en curso
 * (historia #24) y dispara su cobro.
 *
 * El viaje llega resuelto y validado: que sea del conductor autenticado lo
 * garantiza `RidePolicy::complete()` y que siga en `in_progress` lo
 * garantiza `CompleteRideRequest`, mismo reparto que en `StartRideAction`.
 * Tampoco hace falta lock por el mismo motivo que allá: el único que compite
 * por esta fila es el mismo conductor tocando el botón dos veces.
 *
 * Desde la historia #87, `final_fare` es siempre igual a `estimated_fare`:
 * el precio es fijo por sitio, no depende de la distancia/tiempo realmente
 * recorridos, así que no hay nada que recalcular (a diferencia de antes,
 * cuando `CalculateFareAction` volvía a cotizar con la duración real del
 * viaje). Se reconstruye igual un `FareBreakdown` degenerado (todo el monto
 * en `base`, el resto en cero) porque `ChargeRideAction` y el recibo
 * (historias #25/#26) siguen esperando ese tipo — mismo criterio que
 * `CalculateSiteFareAction`.
 */
final readonly class CompleteRideAction
{
    public function __construct(private ChargeRideAction $chargeRide) {}

    public function handle(Ride $ride): Ride
    {
        $completedAt = now();

        $fare = new FareBreakdown(
            currency: $ride->currency,
            base: $ride->estimated_fare,
            distance: 0,
            time: 0,
            waiting: 0,
            subtotal: $ride->estimated_fare,
            total: $ride->estimated_fare,
            minimumApplied: false,
        );

        $ride->update([
            'status' => RideStatus::Completed,
            'completed_at' => $completedAt,
            'final_fare' => $fare->total,
        ]);

        // El cobro se procesa acá, ya con `final_fare` persistido (historia
        // #25), pasándole el mismo `$fare` que lo produjo: es el desglose que
        // `ChargeRideAction` persiste en el pago para el recibo (historia
        // #26). Un fallo del proveedor de pago no interrumpe esta Action ni
        // deja el viaje sin completar: `ChargeRideAction` lo atrapa y
        // devuelve un `Payment` en `failed`.
        $this->chargeRide->handle($ride, $fare);

        // Cierra el ciclo de vida para el pasajero que sigue el viaje por el
        // canal privado (historia #21).
        RideStatusChanged::dispatch($ride->id, $ride->status, $ride->driver_id);

        return $ride;
    }
}
