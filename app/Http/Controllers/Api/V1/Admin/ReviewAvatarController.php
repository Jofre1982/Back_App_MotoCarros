<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\ReviewAvatarAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewAvatarRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;

class ReviewAvatarController extends Controller
{
    public function __invoke(ReviewAvatarRequest $request, ReviewAvatarAction $review, User $user): AdminUserResource
    {
        return new AdminUserResource($review->handle($user, $request->reason())->load('driverProfile'));
    }
}
