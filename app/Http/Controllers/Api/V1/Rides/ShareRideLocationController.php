<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Actions\Realtime\ShareDriverLocationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\ShareRideLocationRequest;
use App\Models\Ride;
use Illuminate\Http\Response;

/**
 * POST /api/v1/rides/{ride}/location
 *
 * Publica la ubicación del conductor asignado en el canal `ride.{id}`
 * (historia #20). Que el conductor autenticado sea el asignado lo resuelve
 * `RidePolicy::shareLocation()` desde `ShareRideLocationRequest`, y que el
 * viaje siga `in_progress`, el propio Form Request.
 *
 * Responde 204 y no un API Resource porque no hay ningún recurso que
 * devolver: la ubicación no se persiste, solo se transmite (ver
 * .claude/STANDARDS.md, "Envelope de las respuestas").
 */
class ShareRideLocationController extends Controller
{
    public function __invoke(
        ShareRideLocationRequest $request,
        ShareDriverLocationAction $shareLocation,
        Ride $ride,
    ): Response {
        $shareLocation->handle($ride, $request->location());

        return response()->noContent();
    }
}
