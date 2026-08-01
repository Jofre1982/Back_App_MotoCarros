<?php

declare(strict_types=1);

namespace App\Actions\Rides;

use App\Enums\RatedRole;
use App\Models\Ride;
use App\Models\RideRating;

/**
 * Registra la calificación que el pasajero da al conductor de un viaje ya
 * completado (historia #27).
 *
 * El viaje llega resuelto y validado: que sea del pasajero autenticado lo
 * garantiza `RidePolicy::rateDriver()` y que esté `completed` sin
 * calificación previa lo garantiza `RateDriverRequest`, mismo reparto que en
 * `CompleteRideAction`.
 */
final readonly class RateDriverAction
{
    public function handle(Ride $ride, int $score, ?string $comment): RideRating
    {
        return $ride->driverRating()->create([
            'rated_role' => RatedRole::Driver,
            'score' => $score,
            'comment' => $comment,
        ]);
    }
}
