<?php

declare(strict_types=1);

namespace App\Actions\Errands;

use App\DTOs\ErrandRequest;
use App\Enums\ErrandStatus;
use App\Models\Errand;
use App\Models\User;

/**
 * Crea la solicitud de mandado de un pasajero (historia #92).
 *
 * A diferencia de `CreateRideAction`, no calcula ninguna tarifa (no hay
 * precio fijo para mandados) ni avisa a conductores cercanos (no hay
 * matching automático, fuera de alcance de la historia): el mandado nace
 * `requested` y espera a que un conductor disponible lo encuentre en
 * `GET /errands` y lo acepte.
 *
 * La foto, si viene, se guarda en el disco `local` (privado) antes del
 * INSERT, mismo criterio que `UploadDriverDocumentAction`.
 */
final readonly class CreateErrandAction
{
    public function handle(User $passenger, ErrandRequest $request): Errand
    {
        $photoPath = $request->photo?->store('errand-photos', 'local');

        return Errand::create([
            'passenger_id' => $passenger->getKey(),
            'status' => ErrandStatus::Requested,
            'description' => $request->description,
            'photo_path' => $photoPath,
            'origin_latitude' => $request->origin->latitude,
            'origin_longitude' => $request->origin->longitude,
            'destination_site_id' => $request->destinationSiteId,
        ]);
    }
}
