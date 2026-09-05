<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\DriverVerificationStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\Date;
use RuntimeException;

/**
 * Aprueba un documento de verificación pendiente (historia técnica #64).
 *
 * Que el documento esté `pending` lo comprueba `ApproveDriverDocumentRequest`
 * antes de llegar acá (422 si no lo está), mismo criterio que
 * `CompleteRideAction` con el estado del viaje: esta Action asume una
 * transición válida y no vuelve a validarla.
 *
 * Si con esta aprobación el conductor queda con **todos** los documentos
 * exigidos (`DocumentType::required()`) en `approved`, su perfil pasa a
 * `verified`. Si todavía falta alguno, el perfil no cambia: puede seguir
 * `pending` (nunca vuelve de `rejected` a `verified` solo por una aprobación
 * parcial, porque nada en este flujo pone a un perfil `rejected` a mitad de
 * camino salvo un rechazo explícito).
 */
final class ApproveDriverDocumentAction
{
    public function handle(DriverDocument $document): DriverDocument
    {
        $document->update([
            'status' => DocumentStatus::Approved,
            'reviewed_at' => Date::now(),
        ]);

        $profile = $document->driverProfile;

        // `driver_profile_id` es NOT NULL (ver la migración), así que esto
        // siempre es cierto: la comprobación es para PHPStan, que no puede
        // inferir el modelo relacionado de una propiedad dinámica, no un caso
        // real. Mismo criterio que `RejectDriverDocumentAction`.
        if (! $profile instanceof DriverProfile) {
            throw new RuntimeException('DriverDocument sin driver_profile asociado.');
        }

        if ($this->tieneTodosLosDocumentosAprobados($profile)) {
            $profile->verification_status = DriverVerificationStatus::Verified;
            $profile->save();
        }

        return $document->refresh();
    }

    private function tieneTodosLosDocumentosAprobados(DriverProfile $profile): bool
    {
        $tiposAprobados = $profile->documents()
            ->where('status', DocumentStatus::Approved)
            ->pluck('type');

        return collect(DocumentType::required())
            ->every(fn (DocumentType $tipo): bool => $tiposAprobados->contains($tipo));
    }
}
