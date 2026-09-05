<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\DTOs\DriverDocumentUpload;
use App\Enums\DocumentStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda el documento de un conductor y lo deja pendiente de revisión.
 *
 * Un documento por tipo y conductor (índice único de `driver_documents`):
 * volver a subir el mismo tipo reemplaza el archivo y la fila existentes en
 * vez de acumular versiones, y el documento vuelve a `Pending` aunque ya
 * hubiera sido aprobado o rechazado —es un archivo distinto, así que hay que
 * revisarlo de nuevo.
 *
 * El archivo se guarda en el disco `local` (privado, `storage/app/private`):
 * son documentos de identidad, y ese disco no se sirve por una URL pública
 * como el disco `public`.
 */
final class UploadDriverDocumentAction
{
    public function handle(DriverProfile $profile, DriverDocumentUpload $upload): DriverDocument
    {
        $existente = $profile->documents()->where('type', $upload->type)->first();

        if ($existente instanceof DriverDocument) {
            Storage::disk('local')->delete($existente->path);
        }

        $path = $upload->file->store("driver-documents/{$profile->id}", 'local');

        return $profile->documents()->updateOrCreate(
            ['type' => $upload->type],
            [
                'path' => $path,
                'status' => DocumentStatus::Pending,
                'rejection_reason' => null,
                'reviewed_at' => null,
            ],
        );
    }
}
