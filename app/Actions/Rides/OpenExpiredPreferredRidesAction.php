<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Services\Push\PushNotifier;
use App\Services\Push\ServiceMessages;
use Illuminate\Support\Facades\Date;

/**
 * Cuando el conductor favorito no tomó el viaje a tiempo, se les avisa a los
 * demás conductores (una sola vez por viaje).
 *
 * No hay tareas programadas en el hosting, así que esto corre cuando alguien
 * consulta: el pasajero que espera su viaje (`GET /rides/{id}`, cada pocos
 * segundos) o un conductor que mira la lista (`GET /rides/available`). La
 * lista ya filtra por la hora, así que el viaje aparece aunque el aviso
 * todavía no haya salido.
 */
final readonly class OpenExpiredPreferredRidesAction
{
    public function __construct(private PushNotifier $push) {}

    public function handle(?Ride $only = null): void
    {
        $now = Date::now();
        $expired = Ride::query()
            ->when($only !== null, fn ($query) => $query->whereKey($only?->getKey()))
            ->where('status', RideStatus::Requested)
            ->whereNotNull('preferred_driver_id')
            ->whereNull('opened_to_all_at')
            ->where('preferred_until', '<=', $now)
            ->get();

        foreach ($expired as $ride) {
            // Marca atómica: si dos consultas llegan juntas, avisa una sola.
            $claimed = Ride::query()
                ->whereKey($ride->getKey())
                ->whereNull('opened_to_all_at')
                ->update(['opened_to_all_at' => $now]);
            if ($claimed !== 1) {
                continue;
            }

            $drivers = array_values(array_diff(
                $this->push->driversForRides($ride->passenger_id),
                [(int) $ride->preferred_driver_id],
            ));
            $this->push->toUsers($drivers, ServiceMessages::newRide());
        }
    }
}
