<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Errands;

use App\Http\Controllers\Controller;
use App\Http\Requests\Errands\ShowErrandPhotoRequest;
use App\Models\Errand;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/v1/errands/{errand}/photo
 *
 * Sirve la foto adjunta al mandado (historia #92), para el pasajero que lo
 * pidió y el conductor asignado. Mismo mecanismo que
 * `ShowDriverDocumentFileController`: el archivo vive en el disco `local`
 * (privado), y `Storage::response()` arma el `Content-Type` real con
 * `Content-Disposition: inline`.
 *
 * El parámetro `Errand $errand` es lo que hace que Laravel resuelva el
 * binding implícito de la ruta; `$request->errand()` reusa esa misma
 * instancia ya con la comprobación de que la foto existe.
 */
class ShowErrandPhotoController extends Controller
{
    public function __invoke(ShowErrandPhotoRequest $request, Errand $errand): StreamedResponse
    {
        /** @var string $path */
        $path = $request->errand()->photo_path;

        return Storage::disk('local')->response($path);
    }
}
