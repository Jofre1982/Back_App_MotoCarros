<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/users/{user}/avatar — ver openapi.yaml.
 */
class ShowAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAvatar', $this->owner()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function owner(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
