<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/admin/sites — ver openapi.yaml.
 */
class ListSitesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Site::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
