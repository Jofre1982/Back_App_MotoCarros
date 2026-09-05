<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Documents\RejectDriverDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectDriverDocumentRequest;
use App\Http\Resources\AdminDriverDocumentResource;
use App\Models\DriverDocument;

/**
 * POST /api/v1/admin/documents/{document}/reject
 *
 * Rechaza un documento de verificación pendiente, con un motivo (historia
 * técnica #64).
 *
 * El parámetro `DriverDocument $document` es lo que hace que Laravel
 * resuelva el binding implícito de la ruta, mismo criterio que
 * `ApproveDriverDocumentController`.
 */
class RejectDriverDocumentController extends Controller
{
    public function __invoke(
        RejectDriverDocumentRequest $request,
        RejectDriverDocumentAction $rejectDocument,
        DriverDocument $document,
    ): AdminDriverDocumentResource {
        $document = $rejectDocument->handle($request->document(), $request->reason());

        return new AdminDriverDocumentResource($document->load('driverProfile.user'));
    }
}
