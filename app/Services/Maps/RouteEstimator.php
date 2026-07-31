<?php

declare(strict_types=1);

namespace App\Services\Maps;

use App\DTOs\Coordinates;
use App\DTOs\RouteEstimate;
use App\Exceptions\RouteEstimationFailed;

/**
 * Contrato con el proveedor de mapas para estimar un trayecto.
 *
 * El resto de la aplicación depende de esta interfaz y nunca de una
 * implementación concreta: la decisión de proveedor está documentada en
 * .claude/STANDARDS.md, pero es reversible, y el costo por request es
 * justamente el motivo por el que conviene poder cambiarla sin tocar el
 * motor de tarifa ni los endpoints.
 */
interface RouteEstimator
{
    /**
     * @throws RouteEstimationFailed si el proveedor falla o no hay ruta entre
     *                               ambos puntos.
     */
    public function estimate(Coordinates $origin, Coordinates $destination): RouteEstimate;
}
