<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\VehicleType;
use App\Models\User;

/**
 * Campos de historial comunes a viajes y mandados (pedido del dueño: saber
 * quién hizo el servicio y con qué vehículo ante una eventualidad).
 */
final class ServiceHistoryFields
{
    /**
     * Placa y tipo guardados al aceptar.
     *
     * @return array{plate: string, type: string}|null
     */
    public static function driverVehicle(?string $plate, ?VehicleType $type): ?array
    {
        if ($plate === null || $type === null) {
            return null;
        }

        return ['plate' => $plate, 'type' => $type->value];
    }

    /**
     * Nombre del pasajero, solo para el conductor asignado.
     */
    public static function passengerNameFor(?User $viewer, ?int $driverId, ?User $passenger): ?string
    {
        if ($viewer === null || $driverId === null || $viewer->getKey() !== $driverId) {
            return null;
        }

        return $passenger?->name;
    }
}
