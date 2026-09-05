<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Las dos categorías de vehículo que opera la app (historia técnica #75).
 *
 * Reemplaza el antiguo campo `model` (texto libre): el año ya identifica el
 * modelo puntual, y lo que importa operativamente —para el servicio y para
 * el censo de vehículos y conductores que la app busca sostener con el
 * tiempo— es si el vehículo es un motocarro o una motocarga.
 */
enum VehicleType: string
{
    case Motocarro = 'motocarro';
    case Motocarga = 'motocarga';
}
