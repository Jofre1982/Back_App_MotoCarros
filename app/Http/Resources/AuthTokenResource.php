<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un access token según el schema `AuthToken` de openapi.yaml.
 *
 * @property-read AuthToken $resource
 */
class AuthTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->accessToken,
            'token_type' => $this->resource->tokenType,
            'expires_in' => $this->resource->expiresInSeconds,
        ];
    }
}
