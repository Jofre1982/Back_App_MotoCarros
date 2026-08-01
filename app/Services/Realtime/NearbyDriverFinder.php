<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use App\DTOs\Coordinates;

/**
 * Qué conductores disponibles hay cerca de un punto (historia #17).
 *
 * Es lo único que necesitan `CreateRideAction`, para saber a quién avisar de
 * una solicitud nueva, y `AcceptRideAction`, para saber a quién avisar de que
 * ya no está disponible: ambas Actions piden lo mismo —conductores
 * disponibles dentro del radio configurado alrededor de un punto— así que
 * comparten esta interfaz en vez de repetir la consulta.
 *
 * Existe como interfaz por el mismo motivo que `RideParticipants`: para que
 * quien la consume no dependa de Eloquent directamente (ver
 * .claude/STANDARDS.md, "Services"). La implementación registrada es
 * {@see EloquentNearbyDriverFinder}.
 */
interface NearbyDriverFinder
{
    /**
     * Ids de los conductores marcados como disponibles, con ubicación
     * conocida, dentro del radio configurado (`config/rides.php`) alrededor
     * de `$point`.
     *
     * @return list<int>
     */
    public function near(Coordinates $point): array;
}
