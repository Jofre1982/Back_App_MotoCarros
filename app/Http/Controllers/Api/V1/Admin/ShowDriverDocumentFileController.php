<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShowDriverDocumentFileRequest;
use App\Models\DriverDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/v1/admin/documents/{document}/file
 *
 * Sirve el archivo (imagen o PDF) de un documento de verificación para que
 * un administrador lo vea antes de aprobar o rechazar (historia técnica
 * #78 — vista previa). El archivo vive en el disco `local` (privado); este
 * es el único punto por el que se puede leer, siempre detrás de `auth:api`
 * y la misma autorización de `review()` que aprobar/rechazar.
 *
 * `Storage::response()` arma la respuesta con el `Content-Type` real del
 * archivo y `Content-Disposition: inline`, que es lo que permite embeberlo
 * en un `<img>` o abrirlo en la pestaña en vez de forzar una descarga.
 */
class ShowDriverDocumentFileController extends Controller
{
    public function __invoke(ShowDriverDocumentFileRequest $request, DriverDocument $document): StreamedResponse
    {
        return Storage::disk('local')->response($document->path);
    }
}
