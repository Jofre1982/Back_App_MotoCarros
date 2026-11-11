<?php

declare(strict_types=1);

namespace App\Http\Requests\Ads;

use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/ads/{ad}/image, POST /ads/{ad}/view y
 * POST /ads/{ad}/click — ver openapi.yaml. Cualquier cuenta autenticada.
 */
class ViewAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->ad()) ?? false;
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
