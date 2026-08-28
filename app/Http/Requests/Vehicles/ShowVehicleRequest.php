<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entrada de GET /api/v1/me/vehicle — ver openapi.yaml.
 */
class ShowVehicleRequest extends FormRequest
{
    private ?Vehicle $vehicle = null;

    /**
     * Mismo orden que `UpdateVehicleRequest::authorize()` y mismo motivo: el
     * rol pesa antes que el recurso, así que el pasajero se lleva 403 y no
     * 404 aunque tampoco tenga vehículo.
     */
    public function authorize(): bool
    {
        $driver = $this->user();

        if (! $driver instanceof User || ! $driver->can('create', Vehicle::class)) {
            return false;
        }

        return $driver->can('view', $this->vehicle());
    }

    /**
     * La moto de la cuenta autenticada, o 404 si todavía no registró
     * ninguna. Mismo criterio que `UpdateVehicleRequest::vehicle()`: se
     * resuelve por la relación y nunca por un id de la entrada.
     */
    public function vehicle(): Vehicle
    {
        if ($this->vehicle instanceof Vehicle) {
            return $this->vehicle;
        }

        $driver = $this->user();
        $vehicle = $driver instanceof User ? $driver->vehicle : null;

        if (! $vehicle instanceof Vehicle) {
            throw new NotFoundHttpException('No tienes un vehículo registrado.');
        }

        return $this->vehicle = $vehicle;
    }

    /**
     * Un GET no trae cuerpo, y el único dato que decide esta operación es el
     * vehículo de la cuenta autenticada.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
