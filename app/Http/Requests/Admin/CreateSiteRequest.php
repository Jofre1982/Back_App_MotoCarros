<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/admin/sites — ver openapi.yaml.
 */
class CreateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Site::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // `unique` acá y no solo el índice de la tabla: sin la regla, un
            // nombre repetido escalaría a un 500 por violación de constraint
            // en vez del 422 que el admin puede entender y corregir.
            'name' => ['required', 'string', 'max:100', 'unique:sites,name'],
        ];
    }

    public function toName(): string
    {
        return $this->string('name')->toString();
    }
}
