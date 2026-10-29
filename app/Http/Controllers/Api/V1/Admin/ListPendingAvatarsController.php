<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPendingAvatarsRequest;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\AvatarUrl;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListPendingAvatarsController extends Controller
{
    public function __invoke(ListPendingAvatarsRequest $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->where('avatar_status', AvatarUrl::PENDING)
            ->with('driverProfile')
            ->orderBy('updated_at')
            ->get();

        return AdminUserResource::collection($users);
    }
}
