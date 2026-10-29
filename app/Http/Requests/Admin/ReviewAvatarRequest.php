<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Resources\AvatarUrl;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/admin/users/{user}/avatar/approve|reject — ver
 * openapi.yaml. Rechazar exige un motivo, que le llega al conductor.
 */
class ReviewAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewAvatar', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->isRejection()
            ? ['reason' => ['required', 'string', 'max:255']]
            : [];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [$this->rejectNothingToReview(...)];
    }

    private function rejectNothingToReview(Validator $validator): void
    {
        if ($this->owner()->avatar_status !== AvatarUrl::PENDING) {
            $validator->errors()->add('avatar', 'Esta cuenta no tiene una foto pendiente de revisión.');
        }
    }

    public function isRejection(): bool
    {
        return $this->route()?->getName() === 'admin.avatars.reject';
    }

    public function reason(): ?string
    {
        return $this->isRejection() ? $this->string('reason')->toString() : null;
    }

    public function owner(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
