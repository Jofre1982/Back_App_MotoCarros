<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\CargoJobType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/admin/cargo-job-types — ver openapi.yaml.
 */
class ListCargoJobTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CargoJobType::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
