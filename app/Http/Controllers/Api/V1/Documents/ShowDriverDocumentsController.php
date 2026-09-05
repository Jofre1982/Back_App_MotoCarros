<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ShowDriverDocumentsRequest;
use App\Http\Resources\DriverVerificationResource;

/**
 * GET /api/v1/me/documents
 *
 * Estado de verificación del conductor autenticado: qué documentos ya subió,
 * en qué estado quedó cada uno, y el estado general.
 */
class ShowDriverDocumentsController extends Controller
{
    public function __invoke(ShowDriverDocumentsRequest $request): DriverVerificationResource
    {
        return new DriverVerificationResource($request->driverProfile()->load('documents'));
    }
}
