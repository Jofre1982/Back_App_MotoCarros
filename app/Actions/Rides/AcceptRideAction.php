<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\DTOs\Coordinates;
use App\Enums\RideStatus;
use App\Events\Realtime\RideNoLongerAvailable;
use App\Exceptions\Rides\RideNoLongerAvailableException;
use App\Models\Ride;
use App\Models\User;
use App\Services\Realtime\NearbyDriverFinder;
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
 *
 * Después de confirmar el cambio, avisa a los demás conductores cercanos
 * (historia #17) que la solicitud ya no está disponible; ver
 * `notifyOtherDrivers()`.
 */
final readonly class AcceptRideAction
{
    public function __construct(private NearbyDriverFinder $nearbyDrivers) {}

    /**
     * @throws RideNoLongerAvailableException si el viaje ya no está en
     *                                        `requested` cuando se resuelve
     *                                        el lock.
     */
    public function handle(Ride $ride, User $driver): Ride
    {
        $accepted = DB::transaction(function () use ($ride, $driver): Ride {
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

        $this->notifyOtherDrivers($accepted, $driver);

        return $accepted;
    }

    /**
     * A quién avisar se recalcula en este momento y no se reutiliza la lista
     * de `RideRequested`, que no queda persistida en ningún lado (ver
     * `App\Events\Realtime\RideNoLongerAvailable`). Se dispara después de que
     * la transacción de arriba ya confirmó el cambio, no dentro de ella: un
     * evento en cola no tiene por qué esperar a un lock que ya se liberó.
     */
    private function notifyOtherDrivers(Ride $ride, User $acceptedBy): void
    {
        $origin = new Coordinates((float) $ride->origin_latitude, (float) $ride->origin_longitude);

        $driverIds = array_values(array_diff(
            $this->nearbyDrivers->near($origin),
            [$acceptedBy->getKey()],
        ));

        if ($driverIds === []) {
            return;
        }

        RideNoLongerAvailable::dispatch($ride->id, $driverIds);
    }
}
