<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/admin/ads — ver openapi.yaml.
 */
class ListAdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Ad::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
