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
 * Devuelve los viajes de la cuenta autenticada, del más reciente al más
 * antiguo: los que pidió, si es pasajero (historia #29), o los que le
 * asignaron, si es conductor (historia #30). No trae filtros por fecha o
 * estado, eso queda para una historia aparte; el resumen de ganancias del
 * conductor en un rango de fechas es un endpoint propio
 * (`GET /me/earnings`, `ShowDriverEarningsController`).
 *
 * No hay Action detrás, mismo criterio que `ShowRideController`: leer los
 * viajes ya acotados a la cuenta del token no decide ni cambia nada. Sí hay
 * Policy, a diferencia de `ShowProfileController` —acá el rol sí importa,
 * porque decide **qué relación** se consulta— y la resuelve
 * `RidePolicy::viewHistory()` desde `ShowRideHistoryRequest`.
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

        $usuario = $request->user();

        $relacion = $usuario->isDriver() ? $usuario->driverRides() : $usuario->rides();

        $viajes = $relacion
            ->with('driver', 'destinationSite')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return RideResource::collection($viajes);
    }
}
