<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\ShowRideHistoryRequest;
use App\Http\Resources\RideResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/me/rides
 *
 * Devuelve los viajes del pasajero autenticado, del más reciente al más
 * antiguo (historia #29). Es la vista con la que consulta y audita sus
 * viajes pasados; no trae filtros por fecha o estado, eso queda para una
 * historia aparte.
 *
 * No hay Action detrás, mismo criterio que `ShowRideController`: leer los
 * viajes ya acotados a la cuenta del token no decide ni cambia nada. Sí hay
 * Policy, a diferencia de `ShowProfileController` —acá el rol sí importa,
 * porque el mismo path va a servir el historial de ganancias del conductor
 * (historia #30)— y la resuelve `RidePolicy::viewHistory()` desde
 * `ShowRideHistoryRequest`.
 */
class ShowRideHistoryController extends Controller
{
    /**
     * Tope de `per_page` aunque el cliente pida más: es el límite razonable
     * que el criterio de aceptación de la #29 deja a criterio del backend.
     */
    private const int MAX_PER_PAGE = 50;

    private const int DEFAULT_PER_PAGE = 15;

    public function __invoke(ShowRideHistoryRequest $request): AnonymousResourceCollection
    {
        $perPage = min(
            $request->integer('per_page', self::DEFAULT_PER_PAGE),
            self::MAX_PER_PAGE,
        );

        $viajes = $request->user()
            ->rides()
            ->with('driver')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return RideResource::collection($viajes);
    }
}
