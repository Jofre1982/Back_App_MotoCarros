<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Entrada de POST /api/v1/admin/users/{id}/gift-months — ver openapi.yaml.
 */
class GiftSubscriptionMonthsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('giftSubscription', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'months' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectAccountWithoutDriverProfile(...),
        ];
    }

    private function rejectAccountWithoutDriverProfile(Validator $validator): void
    {
        if ($this->target()->driverProfile === null) {
            $validator->errors()->add('user', 'Esta cuenta no tiene perfil de conductor.');
        }
    }

    public function target(): User
    {
        return $this->route('user');
    }

    public function months(): int
    {
        return $this->integer('months');
    }
}
