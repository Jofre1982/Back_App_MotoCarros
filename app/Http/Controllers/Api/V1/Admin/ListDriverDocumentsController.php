<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListDriverDocumentsRequest;
use App\Http\Resources\AdminDriverDocumentResource;
use App\Models\DriverDocument;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/admin/documents
 *
 * Lista los documentos de verificación de conductores para que un
 * administrador los revise (historia técnica #64). Sin `status`, lista los
 * `pending` — es la cola de trabajo del administrador.
 */
class ListDriverDocumentsController extends Controller
{
    public function __invoke(ListDriverDocumentsRequest $request): AnonymousResourceCollection
    {
        $documents = DriverDocument::query()
            ->with('driverProfile.user')
            ->where('status', $request->status())
            ->latest()
            ->get();

        return AdminDriverDocumentResource::collection($documents);
    }
}
