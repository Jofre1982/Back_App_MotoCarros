<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Favorites;

use App\Http\Controllers\Controller;
use App\Http\Requests\Favorites\ListFavoriteDriversRequest;
use App\Http\Resources\FavoriteDriverResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListFavoriteDriversController extends Controller
{
    public function __invoke(ListFavoriteDriversRequest $request): AnonymousResourceCollection
    {
        /** @var User $passenger */
        $passenger = $request->user();

        return FavoriteDriverResource::collection(
            $passenger->favoriteDrivers()->with('vehicle', 'driverProfile')->orderBy('name')->get(),
        );
    }
}
