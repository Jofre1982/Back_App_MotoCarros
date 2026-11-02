<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\DTOs\PushNotification;
use App\Enums\VehicleType;
use App\Models\DeviceToken;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use App\Services\Realtime\PushNotificationGateway;

use function Illuminate\Support\defer;

/**
 * Manda notificaciones push a cuentas (a cada celular que tengan
 * registrado) y sabe a qué conductores avisar de un servicio nuevo.
 *
 * El envío se difiere hasta después de responder (`defer`): el pasajero o el
 * conductor no esperan a Firebase para ver el resultado de lo que tocaron.
 */
final readonly class PushNotifier
{
    public function __construct(private PushNotificationGateway $gateway) {}

    /**
     * @param  list<int>  $userIds
     */
    public function toUsers(array $userIds, PushNotification $notification): void
    {
        if ($userIds === []) {
            return;
        }

        defer(fn () => DeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->get()
            ->each(fn (DeviceToken $token) => $this->gateway->send($token, $notification)));
    }

    public function toUser(?int $userId, PushNotification $notification): void
    {
        if ($userId !== null) {
            $this->toUsers([$userId], $notification);
        }
    }

    /**
     * Conductores con "Recibir viajes" encendido y vehículo motocarro (los
     * viajes de pasajero no son para motocarga). Todo el pueblo, igual que
     * la lista de "Solicitudes cercanas".
     *
     * @return list<int>
     */
    public function driversForRides(int $exceptUserId): array
    {
        $available = DriverProfile::query()->where('is_available', true)->pluck('user_id')->all();

        return $this->withVehicleType($available, VehicleType::Motocarro, $exceptUserId);
    }

    /**
     * Conductores con "Recibir mandados y motocargas" encendido y el tipo
     * de vehículo que pide el mandado, igual que `GET /errands`.
     *
     * @return list<int>
     */
    public function driversForErrands(VehicleType $type, int $exceptUserId): array
    {
        $available = DriverProfile::query()->where('is_available_for_errands', true)->pluck('user_id')->all();

        return $this->withVehicleType($available, $type, $exceptUserId);
    }

    /**
     * Un conductor sin vehículo registrado cuenta como motocarro, igual que
     * `User::vehicleType()`.
     *
     * @param  array<int, mixed>  $userIds
     * @return list<int>
     */
    private function withVehicleType(array $userIds, VehicleType $type, int $exceptUserId): array
    {
        $ids = array_values(array_diff(array_map(intval(...), $userIds), [$exceptUserId]));
        $motocargas = Vehicle::query()
            ->whereIn('user_id', $ids)
            ->where('type', VehicleType::Motocarga)
            ->pluck('user_id')
            ->map(intval(...))
            ->all();

        return array_values($type === VehicleType::Motocarga
            ? array_intersect($ids, $motocargas)
            : array_diff($ids, $motocargas));
    }
}
