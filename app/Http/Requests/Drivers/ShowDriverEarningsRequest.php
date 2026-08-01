<?php

declare(strict_types=1);

namespace App\Http\Requests\Drivers;

use App\Models\Ride;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Entrada de GET /api/v1/me/earnings — ver openapi.yaml.
 */
class ShowDriverEarningsRequest extends FormRequest
{
    /**
     * Autorización por clase y no por instancia, mismo criterio que
     * `ShowRideHistoryRequest`: no hay una fila concreta que resolver, la
     * consulta ya viene acotada a la cuenta del token. Lo decide
     * `RidePolicy::viewEarnings()`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewEarnings', Ride::class) ?? false;
    }

    /**
     * `to` valida `after_or_equal:from` para que el criterio de aceptación
     * ("from posterior a to es 422") salga del validador de Laravel en vez de
     * una comprobación manual en el controller.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ];
    }

    public function from(): Carbon
    {
        return Carbon::parse((string) $this->input('from'));
    }

    public function to(): Carbon
    {
        return Carbon::parse((string) $this->input('to'));
    }
}
