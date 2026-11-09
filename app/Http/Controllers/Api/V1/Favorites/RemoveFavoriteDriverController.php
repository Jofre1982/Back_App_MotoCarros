<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Actions\Favorites\RemoveFavoriteDriverAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Favorites\RemoveFavoriteDriverRequest;
use Illuminate\Http\Response;

class RemoveFavoriteDriverController extends Controller
{
    public function __invoke(RemoveFavoriteDriverRequest $request, RemoveFavoriteDriverAction $remove, int $driver): Response
    {
        $remove->handle($request->passenger(), $driver);

        return response()->noContent();
    }
}
