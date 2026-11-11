<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ads\ViewAdRequest;
use App\Models\Ad;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/v1/ads/{ad}/image
 */
class ShowAdImageController extends Controller
{
    public function __invoke(ViewAdRequest $request, Ad $ad): StreamedResponse
    {
        $path = $ad->image_path;
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            throw new NotFoundHttpException('Este anuncio no tiene imagen.');
        }

        return Storage::disk('local')->response($path);
    }
}
