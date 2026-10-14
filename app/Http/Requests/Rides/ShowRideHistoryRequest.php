<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\UserRole;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/me/rides — ver openapi.yaml.
 */
class ShowRideHistoryRequest extends FormRequest
{
    /**
     * Autorización por clase y no por instancia: no hay un viaje concreto que
     * resolver acá, la consulta siempre está acotada a la cuenta del token
     * (historia #29). Lo decide `RidePolicy::viewHistory()`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewHistory', Ride::class) ?? false;
    }

    /**
     * `per_page` no lleva `max` a propósito: superarlo no es una entrada
     * inválida, el backend lo acota a un límite razonable en vez de
     * responder 422 (ver `ShowRideHistoryController`).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'as' => ['sometimes', 'string', 'in:passenger,driver'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [$this->rejectDriverHistoryForNonDriver(...)];
    }

    private function rejectDriverHistoryForNonDriver(Validator $validator): void
    {
        $user = $this->user();

        if ($this->input('as') === 'driver' && $user instanceof User && ! $user->isDriver()) {
            $validator->errors()->add('as', 'Tu cuenta no tiene el modo conductor activo.');
        }
    }

    /**
     * Qué historial ver (historia #99). Sin `as`, el del rol con el que se
     * registró la cuenta, como antes de la historia.
     */
    public function asDriver(): bool
    {
        $as = $this->input('as');

        if (is_string($as)) {
            return $as === 'driver';
        }

        return $this->user()?->role === UserRole::Driver;
    }
}
