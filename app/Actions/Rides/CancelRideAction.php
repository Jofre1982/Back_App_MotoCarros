<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\DTOs\RideCancellation;
use App\Enums\RideStatus;
use App\Models\Ride;

/**
 * Cancela un viaje del pasajero autenticado, ya sea que todavía no lo haya
 * visto aceptado (historia #16) o que un conductor ya lo haya aceptado
 * (historia #22) — `CancelRideRequest` es quien decide cuáles estados llegan
 * hasta acá, así que esta Action no distingue el caso salvo para calcular si
 * corresponde penalización.
 *
 * No hace falta tocar `driver_id`: el conductor asignado queda en la fila
 * como registro histórico de a quién se le canceló, y `active_driver_id` es
 * una columna generada por la base que se recalcula sola al salir de los
 * estados activos, igual que `active_passenger_id` (ver la migración de
 * `rides`), así que el conductor queda libre para aceptar otro viaje sin que
 * esta Action escriba nada más.
 */
final readonly class CancelRideAction
{
    public function handle(Ride $ride): RideCancellation
    {
        // Solo cancelar un viaje ya `accepted` implica que un conductor se
        // había comprometido y se desplazó hacia el punto de recogida; por
        // eso la penalización depende del estado *antes* de cancelar, y no
        // de si el viaje terminó con conductor asignado.
        $feeApplies = $ride->status === RideStatus::Accepted;

        $ride->update(['status' => RideStatus::Cancelled]);

        return new RideCancellation($ride, $feeApplies);
    }
}
