<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Distancia y duración estimadas de un trayecto, ya normalizadas a unidades
 * del sistema internacional.
 *
 * Se normaliza acá y no en quien consume el dato para que el motor de tarifa
 * (issue #4) no tenga que saber si el proveedor de turno devolvió metros,
 * millas o una duración con formato propio.
 */
final readonly class RouteEstimate
{
    public function __construct(
        public int $distanceMeters,
        public int $durationSeconds,
    ) {}
}
