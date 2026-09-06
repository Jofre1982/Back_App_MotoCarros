<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Site;

/**
 * Estimación de un viaje que todavía no se solicitó (historia #14, adaptada
 * en #87 al precio fijo por sitio): el sitio de destino elegido, para cuántos
 * pasajeros, y lo que costaría.
 *
 * Ya no lleva un `RouteEstimate` del proveedor de mapas: el precio sale del
 * catálogo de sitios (`SiteFare`), no de una distancia/duración calculada.
 */
final readonly class RideEstimate
{
    public function __construct(
        public Site $destinationSite,
        public int $passengerCount,
        public string $currency,
        public int $fare,
    ) {}
}
