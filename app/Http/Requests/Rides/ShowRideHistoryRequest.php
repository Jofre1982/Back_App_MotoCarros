<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Models\Ride;
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
