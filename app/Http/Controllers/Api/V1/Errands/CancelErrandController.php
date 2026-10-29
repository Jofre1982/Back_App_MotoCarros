<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Errands;

use App\Actions\Errands\CancelErrandAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Errands\CancelErrandRequest;
use App\Http\Resources\ErrandResource;
use App\Models\Errand;
use App\Models\User;
use RuntimeException;

class CancelErrandController extends Controller
{
    public function __invoke(CancelErrandRequest $request, CancelErrandAction $cancel, Errand $errand): ErrandResource
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new RuntimeException('CancelErrandRequest sin cuenta autenticada.');
        }

        return new ErrandResource($cancel->handle($errand, $actor)->load('driver', 'destinationSite'));
    }
}
