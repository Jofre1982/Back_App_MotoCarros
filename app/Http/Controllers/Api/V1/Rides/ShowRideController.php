<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\ShowRideRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;

/**
 * GET /api/v1/rides/{ride}
 *
 * Devuelve el estado actual del viaje y, si ya lo aceptó alguien, quién es el
 * conductor asignado (historia #21). Es la consulta puntual que acompaña al
 * canal privado `ride.{id}`: el seguimiento en vivo llega por el canal, y este
 * endpoint responde "¿en qué punto está mi viaje?" cuando el cliente arranca,
 * vuelve del segundo plano o perdió la conexión.
 *
 * No hay Action detrás, mismo criterio que `ShowProfileController`: leer un
 * viaje ya resuelto por el binding no decide ni cambia nada, y envolverlo en
 * una Action sería un pasamanos. Sí hay Policy —a diferencia del perfil—
 * porque acá el recurso se pide por id y sí existe la pregunta de si es suyo;
 * la resuelve `RidePolicy::view()` desde `ShowRideRequest`.
 */
class ShowRideController extends Controller
{
    public function __invoke(ShowRideRequest $request, Ride $ride): RideResource
    {
        // La relación se pide explícitamente en vez de dejar que el Resource
        // la cargue sola al serializar: es la única consulta extra de este
        // endpoint y así queda a la vista de quien lea el controller.
        return new RideResource($ride->loadMissing('driver'));
    }
}
