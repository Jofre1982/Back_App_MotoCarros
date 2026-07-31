<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Estimación de un viaje que todavía no se solicitó: el trayecto que devolvió
 * el proveedor de mapas junto con lo que costaría según la tarifa vigente.
 *
 * Combina ambos en un solo objeto para que el controller tenga una sola cosa
 * que pasarle al API Resource, en vez de manejar `RouteEstimate` y
 * `FareBreakdown` como dos parámetros sueltos.
 */
final readonly class RideEstimate
{
    public function __construct(
        public RouteEstimate $route,
        public FareBreakdown $fare,
    ) {}
}
