<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Documents\ApproveDriverDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveDriverDocumentRequest;
use App\Http\Resources\AdminDriverDocumentResource;
use App\Models\DriverDocument;

/**
 * POST /api/v1/admin/documents/{document}/approve
 *
 * Aprueba un documento de verificación pendiente (historia técnica #64).
 *
 * El parámetro `DriverDocument $document` es lo que hace que Laravel
 * resuelva el binding implícito de la ruta, igual que en
 * `CompleteRideController`; sin él, `$request->document()` recibiría el id
 * en crudo en vez del modelo.
 */
class ApproveDriverDocumentController extends Controller
{
    public function __invoke(
        ApproveDriverDocumentRequest $request,
        ApproveDriverDocumentAction $approveDocument,
        DriverDocument $document,
    ): AdminDriverDocumentResource {
        $document = $approveDocument->handle($request->document());

        return new AdminDriverDocumentResource($document->load('driverProfile.user'));
    }
}
