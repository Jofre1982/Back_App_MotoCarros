<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\DriverDocument;
use App\Models\User;

/**
 * URL de la foto del vehículo de un conductor (documento `foto_vehiculo`),
 * solo si el admin la aprobó: es la que el pasajero ve para reconocer el
 * motocarro por su color y características.
 */
final class VehiclePhotoUrl
{
    public static function forOthers(User $driver): ?string
    {
        return self::approvedPhoto($driver) === null ? null : route('users.vehicle-photo', $driver);
    }

    public static function approvedPhoto(User $driver): ?DriverDocument
    {
        return $driver->driverProfile?->documents()
            ->where('type', DocumentType::FotoVehiculo)
            ->where('status', DocumentStatus::Approved)
            ->first();
    }
}
