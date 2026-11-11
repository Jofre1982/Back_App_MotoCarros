<?php

declare(strict_types=1);

namespace App\Http\Requests\Ads;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/ads — ver openapi.yaml. Cualquier cuenta
 * autenticada; la app solo se los muestra al pasajero.
 */
class ListActiveAdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
