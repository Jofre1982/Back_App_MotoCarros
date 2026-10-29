<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ShowAvatarRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowAvatarController extends Controller
{
    public function __invoke(ShowAvatarRequest $request, User $user): StreamedResponse
    {
        $path = $request->owner()->avatar_path;
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            throw new NotFoundHttpException('Esta cuenta no tiene foto de perfil.');
        }

        return Storage::disk('local')->response($path);
    }
}
