<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Errands;

use App\Http\Controllers\Controller;
use App\Http\Requests\Errands\ListMyErrandsRequest;
use App\Http\Resources\ErrandResource;
use App\Models\Errand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListMyErrandsController extends Controller
{
    private const int LIMIT = 50;

    public function __invoke(ListMyErrandsRequest $request): AnonymousResourceCollection
    {
        $column = $request->asDriver() ? 'driver_id' : 'passenger_id';

        $errands = Errand::query()
            ->where($column, $request->user()?->getKey())
            ->with('driver', 'destinationSite')
            ->latest()
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        return ErrandResource::collection($errands);
    }
}
