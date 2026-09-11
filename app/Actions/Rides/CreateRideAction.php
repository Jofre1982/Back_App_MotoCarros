<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Actions\Payments\CalculateSiteFareAction;
use App\DTOs\Coordinates;
use App\DTOs\PushNotification;
use App\DTOs\RideRequest;
use App\Enums\RideStatus;
use App\Enums\VehicleType;
use App\Events\Realtime\RideRequested;
use App\Models\DeviceToken;
use App\Models\Ride;
use App\Models\Site;
use App\Models\User;
use App\Services\Realtime\NearbyDriverFinder;
use App\Services\Realtime\PushNotificationGateway;

/**
 * Crea la solicitud de viaje de un pasajero.
 *
 * El pasajero llega como parámetro y no dentro del DTO: es lo que garantiza que
 * el viaje sea siempre de quien invoca el caso de uso (el usuario que resolvió
 * el guard) y nunca de lo que venga en la entrada.
 *
 * La tarifa se calcula acá y se guarda con el viaje, con el mismo motor que
 * usa `POST /rides/estimate` (`CalculateSiteFareAction`, historia #87): si
 * cada lado calculara lo suyo, el pasajero podría ver un número antes de
 * pedir el viaje y otro después de pedirlo. Queda guardada en vez de
 * recalcularse al consultar el viaje porque es el número que el pasajero
 * aceptó.
 *
 * No hay transacción: se escribe una sola fila.
 *
 * Al final avisa a los conductores cercanos disponibles (historia #17): quién
 * es "cercano" lo resuelve `NearbyDriverFinder`, y si no hay ninguno no se
 * dispara ningún evento —un `RideRequested` sin conductores a quién llegarle
 * no le sirve a nadie.
 *
 * Además de avisarles por el canal `driver.{id}` (que solo llega con la app
 * abierta), les manda una notificación push por cada dispositivo que tengan
 * registrado (historia #67): es el aviso que sí llega con la app cerrada. Un
 * conductor cercano sin ningún `DeviceToken` no genera ningún error, solo se
 * queda sin ese aviso adicional.
 */
final readonly class CreateRideAction
{
    public function __construct(
        private CalculateSiteFareAction $calculateFare,
        private NearbyDriverFinder $nearbyDrivers,
        private PushNotificationGateway $pushGateway,
    ) {}

    public function handle(User $passenger, RideRequest $request): Ride
    {
        $site = Site::query()->findOrFail($request->destinationSiteId);
        $fare = $this->calculateFare->handle($site, VehicleType::Motocarro, $request->passengerCount);

        $ride = Ride::create([
            'passenger_id' => $passenger->getKey(),
            'status' => RideStatus::Requested,
            'origin_latitude' => $request->origin->latitude,
            'origin_longitude' => $request->origin->longitude,
            'destination_site_id' => $site->getKey(),
            'passenger_count' => $request->passengerCount,
            'currency' => $fare->currency,
            'estimated_fare' => $fare->total,
        ]);

        $this->notifyNearbyDrivers($ride, $request->origin, $site);

        return $ride;
    }

    private function notifyNearbyDrivers(Ride $ride, Coordinates $origin, Site $site): void
    {
        $driverIds = $this->nearbyDrivers->near($origin);

        if ($driverIds === []) {
            return;
        }

        RideRequested::dispatch(
            rideId: $ride->id,
            originLatitude: $ride->origin_latitude,
            originLongitude: $ride->origin_longitude,
            destinationSiteId: $site->getKey(),
            destinationSiteName: $site->name,
            passengerCount: $ride->passenger_count,
            currency: $ride->currency,
            estimatedFare: $ride->estimated_fare,
            nearbyDriverIds: $driverIds,
        );

        $this->sendPushToNearbyDrivers($driverIds);
    }

    /**
     * @param  list<int>  $driverIds
     */
    private function sendPushToNearbyDrivers(array $driverIds): void
    {
        $notification = new PushNotification(
            title: 'Nuevo viaje cerca de ti',
            body: 'Hay un pasajero esperando cerca. Abre la app para ver el detalle.',
        );

        DeviceToken::query()
            ->whereIn('user_id', $driverIds)
            ->get()
            ->each(fn (DeviceToken $token) => $this->pushGateway->send($token, $notification));
    }
}
