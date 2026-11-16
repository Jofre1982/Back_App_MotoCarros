<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de DELETE /api/v1/admin/ads/{ad} — ver openapi.yaml.
 */
class DeleteAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->ad()) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function ad(): Ad
    {
        /** @var Ad */
        return $this->route('ad');
    }
}
