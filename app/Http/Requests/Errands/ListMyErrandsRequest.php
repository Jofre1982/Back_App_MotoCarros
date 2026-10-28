<?php

declare(strict_types=1);

namespace App\Http\Requests\Errands;

use App\Models\Errand;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/me/errands — ver openapi.yaml.
 */
class ListMyErrandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewOwn', Errand::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'as' => ['sometimes', 'string', 'in:passenger,driver'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [$this->rejectDriverViewForNonDriver(...)];
    }

    /**
     * Mismo criterio que `ShowRideHistoryRequest`: pedir la vista de
     * conductor sin tener ese modo es un 422, no una lista vacía.
     */
    private function rejectDriverViewForNonDriver(Validator $validator): void
    {
        $user = $this->user();

        if ($this->asDriver() && $user instanceof User && ! $user->isDriver()) {
            $validator->errors()->add('as', 'Tu cuenta no tiene el modo conductor activo.');
        }
    }

    public function asDriver(): bool
    {
        return $this->input('as') === 'driver';
    }
}
