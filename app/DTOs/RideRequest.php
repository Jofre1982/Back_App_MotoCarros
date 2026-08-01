<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Lo que un pasajero pide cuando solicita un viaje: de dónde a dónde.
 *
 * No lleva pasajero, por la misma razón por la que `VehicleRegistration` no
 * lleva dueño: quién pide el viaje lo decide quien invoca la Action —el guard,
 * en el caso del endpoint— y no los datos que manda el cliente. Tampoco lleva
 * estado ni tarifa: el viaje nace siempre en `requested` y el monto sale del
 * motor de tarifa, así que ninguno de los dos es una entrada.
 */
final readonly class RideRequest
{
    public function __construct(
        public Coordinates $origin,
        public Coordinates $destination,
    ) {}
}
