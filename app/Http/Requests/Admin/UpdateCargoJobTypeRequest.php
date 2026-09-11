<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\CargoJobType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de PATCH /api/v1/admin/cargo-job-types/{cargoJobType} — ver
 * openapi.yaml.
 */
class UpdateCargoJobTypeRequest extends FormRequest
{
    /**
     * `{cargoJobType}` resuelve por binding implícito de la ruta: un id
     * inexistente sale como 404 antes de que se evalúe `authorize()`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->cargoJobType()) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'integer', 'min:0'],
        ];
    }

    public function cargoJobType(): CargoJobType
    {
        /** @var CargoJobType */
        return $this->route('cargoJobType');
    }

    public function toPrice(): int
    {
        return $this->integer('price');
    }
}
