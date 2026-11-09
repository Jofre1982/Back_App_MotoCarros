<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/me/favorite-drivers — ver openapi.yaml.
 */
class ListFavoriteDriversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
