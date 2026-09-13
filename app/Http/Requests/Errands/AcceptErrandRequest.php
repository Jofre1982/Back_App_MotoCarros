<?php

declare(strict_types=1);

namespace App\Http\Requests\Errands;

use App\Models\Errand;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/errands/{errand}/accept — ver openapi.yaml.
 */
class AcceptErrandRequest extends FormRequest
{
    /**
     * El mandado llega resuelto por el binding implícito de la ruta, igual
     * que `AcceptRideRequest`. El permiso es del rol conductor y no depende
     * de esta fila: cualquier conductor puede intentar aceptar cualquier
     * mandado `requested`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('accept', Errand::class) ?? false;
    }

    /**
     * `agreed_price` es el único campo: es lo que el conductor negoció con
     * el pasajero por fuera del sistema (historia #92, no hay tarifa fija).
     * Entero en la unidad mínima de la moneda, nunca 0 o negativo.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'agreed_price' => ['required', 'integer', 'min:1'],
        ];
    }

    public function agreedPrice(): int
    {
        return $this->integer('agreed_price');
    }
}
