<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

/**
 * Un punto geográfico validado.
 *
 * Existe para que origen y destino no viajen como dos floats sueltos que
 * cualquier capa pueda invertir sin que nadie se entere, y para que un par de
 * coordenadas imposible se rechace en el borde y no en la respuesta del
 * proveedor de mapas.
 */
final readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException("Latitud fuera de rango: {$latitude} (debe estar entre -90 y 90).");
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException("Longitud fuera de rango: {$longitude} (debe estar entre -180 y 180).");
        }
    }

    /**
     * Construye el par desde el formato `"lat,lng"`.
     *
     * Solo lo usa la entrada por consola (`maps:estimate`); los endpoints de la
     * API reciben latitud y longitud como campos separados y validados por su
     * Form Request.
     *
     * @throws InvalidArgumentException si el texto no tiene la forma esperada.
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException("Coordenada inválida: '{$value}'. Formato esperado: 'lat,lng'.");
        }

        return new self((float) $matches[1], (float) $matches[2]);
    }
}
