<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Actions\Payments\CalculateFareAction;
use App\DTOs\RouteEstimate;
use App\Enums\RideStatus;
use App\Events\Realtime\RideStatusChanged;
use App\Models\Ride;

/**
 * Marca como completado un viaje que el conductor asignado tiene en curso
 * (historia #24) y recalcula la tarifa final.
 *
 * El viaje llega resuelto y validado: que sea del conductor autenticado lo
 * garantiza `RidePolicy::complete()` y que siga en `in_progress` lo
 * garantiza `CompleteRideRequest`, mismo reparto que en `StartRideAction`.
 * Tampoco hace falta lock por el mismo motivo que allá: el único que compite
 * por esta fila es el mismo conductor tocando el botón dos veces.
 *
 * La tarifa final sale de la misma `CalculateFareAction` que la estimada al
 * crear el viaje (ver .claude/STANDARDS.md, "Cálculo de tarifas"), con un
 * `RouteEstimate` distinto: no hay tracking de la distancia realmente
 * recorrida (la ubicación del conductor solo se transmite por broadcast, ver
 * `Ride`), así que se reusa la distancia cotizada al pedir el viaje y se
 * reemplaza la duración estimada por el tiempo real transcurrido entre
 * `started_at` y ahora.
 */
final readonly class CompleteRideAction
{
    public function __construct(private CalculateFareAction $calculateFare) {}

    public function handle(Ride $ride): Ride
    {
        $completedAt = now();

        $fare = $this->calculateFare->handle(new RouteEstimate(
            distanceMeters: $ride->estimated_distance_meters,
            durationSeconds: max(0, $completedAt->getTimestamp() - $ride->started_at->getTimestamp()),
        ));

        $ride->update([
            'status' => RideStatus::Completed,
            'completed_at' => $completedAt,
            'final_fare' => $fare->total,
        ]);

        // Cierra el ciclo de vida para el pasajero que sigue el viaje por el
        // canal privado (historia #21); el cobro en sí queda fuera de esta
        // historia (lo resuelve "Pagar el viaje al finalizar").
        RideStatusChanged::dispatch($ride->id, $ride->status, $ride->driver_id);

        return $ride;
    }
}
