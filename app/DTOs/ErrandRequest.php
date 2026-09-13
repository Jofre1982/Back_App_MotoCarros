<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Http\UploadedFile;

/**
 * Lo que un pasajero pide al solicitar un mandado (historia #92): qué hay
 * que recoger, de dónde y a qué sitio entregarlo, y opcionalmente una foto.
 *
 * No lleva pasajero ni precio, mismo criterio que `RideRequest`: quién lo
 * pide lo decide el guard, y el precio no es una entrada — nace vacío y lo
 * fija el conductor al aceptar.
 */
final readonly class ErrandRequest
{
    public function __construct(
        public string $description,
        public Coordinates $origin,
        public int $destinationSiteId,
        public ?UploadedFile $photo = null,
    ) {}
}
