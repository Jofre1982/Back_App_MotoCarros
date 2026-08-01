<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/{ride}/cancel — ver openapi.yaml.
 */
class CancelRideRequest extends FormRequest
{
    /**
     * El viaje llega resuelto por el binding implícito de la ruta —el
     * `SubstituteBindings` de la ruta corre antes de que el controller
     * resuelva este Form Request—, así que un id inexistente nunca llega
     * hasta acá: sale como 404 antes de que se evalúe `authorize()`.
     *
     * Que el viaje sea del pasajero autenticado se resuelve acá y no en el
     * controller, igual que en `CreateRideRequest`, para que el 403 se
     * resuelva antes que la validación de estado.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->ride()) ?? false;
    }

    /**
     * No hay campos en el cuerpo: todo lo que decide esta operación es el
     * viaje de la ruta y quién lo pide.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectAlreadyAccepted(...),
        ];
    }

    /**
     * Que el viaje ya no esté en `requested` no es un problema de permisos
     * —el pasajero sigue siendo dueño del viaje— sino de en qué punto del
     * ciclo de vida está: por eso es 422 y no 403, y por eso vive acá y no en
     * `RidePolicy::cancel()`. Mismo criterio que "ya tiene un viaje activo"
     * en `CreateRideRequest`, con el error bajo la clave `ride`, que tampoco
     * es un campo de la entrada.
     */
    private function rejectAlreadyAccepted(Validator $validator): void
    {
        if ($this->ride()->status !== RideStatus::Requested) {
            $validator->errors()->add(
                'ride',
                'El viaje ya fue aceptado; usa el flujo de cancelación de viaje aceptado.',
            );
        }
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }
}
