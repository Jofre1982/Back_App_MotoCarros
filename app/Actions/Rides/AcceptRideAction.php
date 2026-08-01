<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Enums\RideStatus;
use App\Exceptions\Rides\RideNoLongerAvailableException;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Asigna un conductor a un viaje disponible (historia #18).
 *
 * Dos conductores pueden intentar aceptar el mismo viaje casi al mismo tiempo
 * —es el punto central de la historia, no un detalle—, así que el estado no
 * se comprueba sobre el `$ride` que llegó del binding de la ruta: pudo quedar
 * desactualizado entre que se cargó y que se ejecuta esta Action. Se relee con
 * `lockForUpdate()` dentro de una transacción, así que el segundo conductor en
 * llegar espera a que la primera transacción termine y encuentra el viaje ya
 * `accepted`.
 *
 * Que el conductor ya tenga un viaje propio activo lo adelanta
 * `AcceptRideRequest` (422); acá ya no queda esa decisión, solo el cambio de
 * estado.
 */
final readonly class AcceptRideAction
{
    /**
     * @throws RideNoLongerAvailableException si el viaje ya no está en
     *                                        `requested` cuando se resuelve
     *                                        el lock.
     */
    public function handle(Ride $ride, User $driver): Ride
    {
        return DB::transaction(function () use ($ride, $driver): Ride {
            /** @var Ride $locked */
            $locked = Ride::query()->whereKey($ride->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== RideStatus::Requested) {
                throw new RideNoLongerAvailableException;
            }

            $locked->update([
                'status' => RideStatus::Accepted,
                'driver_id' => $driver->getKey(),
            ]);

            return $locked;
        });
    }
}
