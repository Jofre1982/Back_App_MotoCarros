<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\CargoJobType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de DELETE /api/v1/admin/cargo-job-types/{cargoJobType} — ver
 * openapi.yaml.
 */
class DeleteCargoJobTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->cargoJobType()) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function cargoJobType(): CargoJobType
    {
        /** @var CargoJobType */
        return $this->route('cargoJobType');
    }
}
