<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\RideMode;

/**
 * Lo que un pasajero pide cuando solicita un viaje: de dónde lo recogen, a
 * dónde va, y para cuántos pasajeros.
 *
 * El origen sigue siendo un punto libre (el conductor tiene que encontrar al
 * pasajero). El destino depende de la modalidad:
 *
 * - `Catalog` (historia #87): un sitio del catálogo con precio fijo
 *   (`destinationSiteId`), no coordenadas GPS.
 * - `PickupOnly` (historia #95): texto libre escrito por el pasajero
 *   (`destinationText`), sin precio; lo acuerda el conductor al aceptar.
 *
 * `passengerCount` importa para el cobro cuando el sitio cobra por persona,
 * y de todos modos es información útil para el conductor.
 *
 * No lleva pasajero, por la misma razón por la que `VehicleRegistration` no
 * lleva dueño: quién pide el viaje lo decide quien invoca la Action —el guard,
 * en el caso del endpoint— y no los datos que manda el cliente. Tampoco lleva
 * estado ni tarifa: el viaje nace siempre en `requested`, y el monto sale del
 * sitio o del acuerdo con el conductor, nunca de la entrada.
 */
final readonly class RideRequest
{
    public function __construct(
        public Coordinates $origin,
        public ?int $destinationSiteId,
        public int $passengerCount,
        public RideMode $mode = RideMode::Catalog,
        public ?string $destinationText = null,
    ) {}

    /**
     * Viaje solo recogida (historia #95).
     */
    public static function pickupOnly(Coordinates $origin, string $destinationText, int $passengerCount): self
    {
        return new self(
            origin: $origin,
            destinationSiteId: null,
            passengerCount: $passengerCount,
            mode: RideMode::PickupOnly,
            destinationText: trim($destinationText),
        );
    }
}
