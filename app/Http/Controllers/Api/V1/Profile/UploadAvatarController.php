<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Actions\Profile\UploadAvatarAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use RuntimeException;

class UploadAvatarController extends Controller
{
    public function __invoke(UploadAvatarRequest $request, UploadAvatarAction $upload): UserResource
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new RuntimeException('UploadAvatarRequest sin cuenta autenticada.');
        }

        return new UserResource($upload->handle($user, $request->photo()));
    }
}
