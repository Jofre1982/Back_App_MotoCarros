<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/admin/avatars — ver openapi.yaml.
 */
class ListPendingAvatarsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewAvatar', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
