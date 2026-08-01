<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\DTOs\RideCancellation;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;

/**
 * Mismo endpoint, dos operaciones distintas según quién cancela — ver
 * `RidePolicy::cancel()` y `CancelRideRequest`, que ya resolvieron que
 * `$actor` es uno de los dos dueños posibles del viaje antes de que esta
 * Action se ejecute:
 *
 * - El **pasajero** cancela un viaje que todavía no vio aceptado (historia
 *   #16) o que un conductor ya aceptó (historia #22): el viaje pasa a
 *   `cancelled`.
 * - El **conductor asignado** cancela un viaje `accepted` que todavía no
 *   inició (historia #23): el viaje no se cancela, vuelve a `requested` sin
 *   conductor, para que otros conductores puedan aceptarlo.
 */
final readonly class CancelRideAction
{
    public function handle(Ride $ride, User $actor): RideCancellation
    {
        if ($ride->driver_id === $actor->getKey()) {
            return $this->returnToPool($ride, $actor);
        }

        return $this->cancel($ride);
    }

    /**
     * No hace falta tocar `driver_id`: el conductor asignado queda en la fila
     * como registro histórico de a quién se le canceló, y `active_driver_id`
     * es una columna generada por la base que se recalcula sola al salir de
     * los estados activos, igual que `active_passenger_id` (ver la migración
     * de `rides`), así que el conductor queda libre para aceptar otro viaje
     * sin que esta Action escriba nada más.
     */
    private function cancel(Ride $ride): RideCancellation
    {
        // Solo cancelar un viaje ya `accepted` implica que un conductor se
        // había comprometido y se desplazó hacia el punto de recogida; por
        // eso la penalización depende del estado *antes* de cancelar, y no
        // de si el viaje terminó con conductor asignado.
        $feeApplies = $ride->status === RideStatus::Accepted;

        $ride->update(['status' => RideStatus::Cancelled]);

        return new RideCancellation($ride, $feeApplies);
    }

    /**
     * `driver_id` sí se limpia acá, al revés que en `cancel()`: el viaje
     * sigue en pie para el pasajero y tiene que volver a aparecer como
     * disponible para cualquier conductor, no solo para el que se bajó.
     *
     * El conteo de cancelaciones queda en el perfil del conductor para uso
     * futuro en políticas de calidad (historia #23); esta historia solo lo
     * registra, no bloquea la cancelación actual ni penaliza con ella. Es
     * `?->` porque el invariante real —todo conductor tiene perfil, ver
     * `RegisterDriverAction`— no lo garantiza esta Action.
     */
    private function returnToPool(Ride $ride, User $driver): RideCancellation
    {
        $ride->update([
            'status' => RideStatus::Requested,
            'driver_id' => null,
        ]);

        $driver->driverProfile?->increment('cancellation_count');

        return new RideCancellation($ride, feeApplies: false);
    }
}
