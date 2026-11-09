<?php

declare(strict_types=1);

namespace App\Actions\Errands;

use App\Enums\ErrandStatus;
use App\Exceptions\Errands\ErrandNoLongerAvailableException;
use App\Models\Errand;
use App\Models\User;
use App\Services\Push\PushNotifier;
use App\Services\Push\ServiceMessages;
use Illuminate\Support\Facades\DB;

/**
 * Asigna un conductor a un mandado disponible, con el precio que negoció con
 * el pasajero (historia #92).
 *
 * Mismo motivo de lock que `AcceptRideAction`: dos conductores pueden
 * intentar aceptar el mismo mandado casi al mismo tiempo, así que el estado
 * se relee con `lockForUpdate()` dentro de una transacción en vez de
 * confiarse en el `$errand` que llegó del binding de la ruta.
 */
final readonly class AcceptErrandAction
{
    public function __construct(private PushNotifier $push) {}

    /**
     * @throws ErrandNoLongerAvailableException si el mandado ya no está en
     *                                          `requested` cuando se resuelve
     *                                          el lock.
     */
    public function handle(Errand $errand, User $driver, int $agreedPrice): Errand
    {
        $accepted = DB::transaction(function () use ($errand, $driver, $agreedPrice): Errand {
            /** @var Errand $locked */
            $locked = Errand::query()->whereKey($errand->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ErrandStatus::Requested) {
                throw new ErrandNoLongerAvailableException;
            }

            $locked->update([
                'status' => ErrandStatus::Accepted,
                'driver_id' => $driver->getKey(),
                'driver_vehicle_plate' => $driver->vehicle?->plate,
                'driver_vehicle_type' => $driver->vehicle?->type,
                'agreed_price' => $agreedPrice,
            ]);

            return $locked;
        });

        $this->push->toUser(
            $accepted->passenger_id,
            ServiceMessages::priceOffered($driver->name, $accepted->agreed_price, ServiceMessages::errandName($accepted->vehicle_type)),
        );

        return $accepted;
    }
}
