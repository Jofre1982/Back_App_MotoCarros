<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Drivers;

use App\Actions\Drivers\UpdateDriverAvailabilityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drivers\UpdateDriverAvailabilityRequest;
use App\Http\Resources\DriverProfileResource;

/**
 * PATCH /api/v1/me/availability
 *
 * Marca al conductor autenticado disponible o no disponible para recibir
 * solicitudes de viaje cercanas, y opcionalmente actualiza su ubicación
 * (historia #17). Qué perfil se toca y si la cuenta puede operarlo ya lo
 * resolvió `UpdateDriverAvailabilityRequest`.
 */
class UpdateDriverAvailabilityController extends Controller
{
    public function __invoke(
        UpdateDriverAvailabilityRequest $request,
        UpdateDriverAvailabilityAction $updateAvailability,
    ): DriverProfileResource {
        $perfil = $updateAvailability->handle($request->driverProfile(), $request->toUpdate());

        return new DriverProfileResource($perfil);
    }
}
