<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Drivers;

use App\Actions\Drivers\UpdateDriverErrandAvailabilityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drivers\UpdateDriverErrandAvailabilityRequest;
use App\Http\Resources\DriverProfileResource;

/**
 * PATCH /api/v1/me/availability/errands
 *
 * Marca al conductor autenticado disponible o no disponible para tomar
 * mandados (historia #92) — pool separado del de viajes
 * (`PATCH /me/availability`). Qué perfil se toca ya lo resolvió
 * `UpdateDriverErrandAvailabilityRequest`.
 */
class UpdateDriverErrandAvailabilityController extends Controller
{
    public function __invoke(
        UpdateDriverErrandAvailabilityRequest $request,
        UpdateDriverErrandAvailabilityAction $updateAvailability,
    ): DriverProfileResource {
        $perfil = $updateAvailability->handle($request->driverProfile(), $request->isAvailable());

        return new DriverProfileResource($perfil);
    }
}
