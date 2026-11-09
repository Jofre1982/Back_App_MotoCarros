<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Actions\Favorites\AddFavoriteDriverAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Favorites\AddFavoriteDriverRequest;
use App\Http\Resources\FavoriteDriverResource;
use Illuminate\Http\JsonResponse;

class AddFavoriteDriverController extends Controller
{
    public function __invoke(AddFavoriteDriverRequest $request, AddFavoriteDriverAction $add): JsonResponse
    {
        $driver = $add->handle($request->passenger(), $request->driver());

        return (new FavoriteDriverResource($driver))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
