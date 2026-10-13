<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `openapi.yaml#/components/schemas/AdminUser` (historia #97).
 *
 * @property User $resource
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'phone_verified' => $this->resource->isPhoneVerified(),
            'role' => $this->resource->role->value,
            'driver_verification_status' => $this->resource->driverProfile?->verification_status->value,
            'registered_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
