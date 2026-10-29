<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\VehicleType;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/rides/available — ver openapi.yaml.
 *
 * Ver la lista es del mismo que puede aceptar (`RidePolicy::accept()`):
 * cualquier conductor.
 */
class ListAvailableRidesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accept', Ride::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Los viajes de pasajero son de motocarro: un conductor con motocarga no
     * tiene nada que ver acá (y aceptarlos le responde 422).
     */
    /**
     * "Recibir viajes" apagado: la lista viene vacía, así el conductor usa
     * la app sin que le lleguen solicitudes.
     */
    public function isAvailableForRides(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->driverProfile?->is_available === true;
    }

    public function drivesMotocarga(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->vehicleType() === VehicleType::Motocarga;
    }
}
