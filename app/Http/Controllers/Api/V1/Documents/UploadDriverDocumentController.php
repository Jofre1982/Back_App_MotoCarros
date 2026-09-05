<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Documents;

use App\Actions\Documents\UploadDriverDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\UploadDriverDocumentRequest;
use App\Http\Resources\DriverDocumentResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/me/documents
 *
 * Sube (o reemplaza) uno de los documentos de verificación del conductor
 * autenticado: documento de identidad, tarjeta de propiedad o foto del
 * vehículo (ver `DocumentType`).
 */
class UploadDriverDocumentController extends Controller
{
    public function __invoke(
        UploadDriverDocumentRequest $request,
        UploadDriverDocumentAction $uploadDocument,
    ): JsonResponse {
        $document = $uploadDocument->handle($request->driverProfile(), $request->toUpload());

        return (new DriverDocumentResource($document))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
