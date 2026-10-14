<?php

declare(strict_types=1);

namespace App\Http\Requests\Errands;

use App\Models\Errand;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [$this->rejectOwnErrand(...)];
    }

    /**
     * Historia #99: una cuenta puede ser pasajero y conductor, pero no puede
     * tomar el mandado que ella misma pidió.
     */
    private function rejectOwnErrand(Validator $validator): void
    {
        $errand = $this->route('errand');

        if ($errand instanceof Errand && $errand->passenger_id === $this->user()?->getKey()) {
            $validator->errors()->add('errand', 'No puedes aceptar un mandado que pediste tú.');
        }
    }
}
