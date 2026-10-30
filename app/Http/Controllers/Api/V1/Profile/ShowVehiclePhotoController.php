<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ShowVehiclePhotoRequest;
use App\Http\Resources\VehiclePhotoUrl;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowVehiclePhotoController extends Controller
{
    public function __invoke(ShowVehiclePhotoRequest $request, User $user): StreamedResponse
    {
        $photo = VehiclePhotoUrl::approvedPhoto($request->owner());
        if ($photo === null || ! Storage::disk('local')->exists($photo->path)) {
            throw new NotFoundHttpException('Este conductor no tiene foto del vehículo aprobada.');
        }

        return Storage::disk('local')->response($photo->path);
    }
}
