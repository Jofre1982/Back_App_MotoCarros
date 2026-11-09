<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de DELETE /api/v1/me/favorite-drivers/{id} — ver openapi.yaml.
 * Solo toca la lista de la cuenta del token.
 */
class RemoveFavoriteDriverRequest extends FormRequest
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

    public function passenger(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
