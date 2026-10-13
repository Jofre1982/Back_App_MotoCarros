<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Modalidad de un viaje (historia #95).
 *
 * - `Catalog`: el destino es un sitio del catálogo y el precio es el fijo de
 *   ese sitio, conocido antes de pedir (historia #87).
 * - `PickupOnly`: el pasajero marca solo dónde lo recogen y escribe a dónde
 *   va; el precio lo acuerdan y lo registra el conductor al aceptar, igual
 *   que en los mandados (historia #92).
 */
enum RideMode: string
{
    case Catalog = 'catalog';
    case PickupOnly = 'pickup_only';
}
