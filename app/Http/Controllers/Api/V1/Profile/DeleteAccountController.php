<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Actions\Profile\DeleteAccountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\DeleteAccountRequest;
use Illuminate\Http\Response;
use Tymon\JWTAuth\JWT;

class DeleteAccountController extends Controller
{
    public function __invoke(DeleteAccountRequest $request, DeleteAccountAction $delete, JWT $jwt): Response
    {
        $delete->handle($request->account(), (string) $jwt->getToken());

        return response()->noContent();
    }
}
