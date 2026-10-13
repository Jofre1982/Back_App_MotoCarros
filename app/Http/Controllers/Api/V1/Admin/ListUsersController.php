<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\CountUsersByRoleAction;
use App\Actions\Admin\ListUsersAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Resources\AdminUserResource;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/admin/users — historia #97.
 */
class ListUsersController extends Controller
{
    public function __invoke(
        ListUsersRequest $request,
        ListUsersAction $listUsers,
        CountUsersByRoleAction $countByRole,
    ): JsonResponse {
        $page = $listUsers->handle($request->role(), $request->search());

        return new JsonResponse([
            'data' => AdminUserResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'totals' => $countByRole->handle(),
            ],
        ]);
    }
}
