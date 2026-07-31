<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa una cuenta y su access token según el schema `AuthenticatedUser`
 * de openapi.yaml.
 *
 * Los resources anidados no repiten el envelope `data`: Laravel solo lo agrega
 * en el nivel superior de la respuesta.
 *
 * @property-read AuthenticatedUser $resource
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource->user),
            'token' => new AuthTokenResource($this->resource->token),
        ];
    }
}
