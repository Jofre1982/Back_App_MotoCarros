<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DriverVerificationStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\Date;
use RuntimeException;

/**
 * Rechaza un documento de verificación pendiente, con un motivo (historia
 * técnica #64).
 *
 * Que el documento esté `pending` lo comprueba `RejectDriverDocumentRequest`
 * antes de llegar acá, mismo criterio que `ApproveDriverDocumentAction`.
 *
 * A diferencia de aprobar, rechazar **siempre** pone el perfil en `rejected`,
 * sin comprobar el resto de los documentos: alcanza con que uno esté mal
 * para que el conductor no pueda operar todavía, y el conductor corrige
 * resubiendo ese documento (`POST /me/documents`, que vuelve a dejarlo
 * `pending`).
 */
final class RejectDriverDocumentAction
{
    public function handle(DriverDocument $document, string $reason): DriverDocument
    {
        $document->update([
            'status' => DocumentStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => Date::now(),
        ]);

        $profile = $document->driverProfile;

        // `driver_profile_id` es NOT NULL (ver la migración), así que esto
        // siempre es cierto: la comprobación es para PHPStan, que no puede
        // inferir el modelo relacionado de una propiedad dinámica, no un caso
        // real. Mismo criterio que `ApproveDriverDocumentAction`.
        if (! $profile instanceof DriverProfile) {
            throw new RuntimeException('DriverDocument sin driver_profile asociado.');
        }

        $profile->verification_status = DriverVerificationStatus::Rejected;
        $profile->save();

        return $document->refresh();
    }
}
