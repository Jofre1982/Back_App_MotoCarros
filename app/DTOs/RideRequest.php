<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Lo que un pasajero pide cuando solicita un viaje: de dónde lo recogen, a
 * qué sitio va, y para cuántos pasajeros.
 *
 * El origen sigue siendo un punto libre (el conductor tiene que encontrar al
 * pasajero); el destino ya no (historia #87): es un sitio del catálogo con
 * precio fijo (ver `Site`/`SiteFare`), no coordenadas GPS. `passengerCount`
 * importa para el cobro cuando el sitio cobra por persona, y de todos modos
 * es informacion util para el conductor sin importar el modo de cobro del
 * sitio.
 *
 * No lleva pasajero, por la misma razón por la que `VehicleRegistration` no
 * lleva dueño: quién pide el viaje lo decide quien invoca la Action —el guard,
 * en el caso del endpoint— y no los datos que manda el cliente. Tampoco lleva
 * estado ni tarifa: el viaje nace siempre en `requested` y el monto sale del
 * precio fijo del sitio, así que ninguno de los dos es una entrada.
 */
final readonly class RideRequest
{
    public function __construct(
        public Coordinates $origin,
        public int $destinationSiteId,
        public int $passengerCount,
    ) {}
}
