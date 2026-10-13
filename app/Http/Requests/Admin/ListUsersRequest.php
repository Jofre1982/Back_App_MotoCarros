<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrada de GET /api/v1/admin/users — ver openapi.yaml (historia #97).
 */
class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'string', Rule::enum(UserRole::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function role(): ?UserRole
    {
        $role = $this->input('role');

        return is_string($role) ? UserRole::tryFrom($role) : null;
    }

    public function search(): ?string
    {
        $search = trim($this->string('search')->toString());

        return $search === '' ? null : $search;
    }
}
