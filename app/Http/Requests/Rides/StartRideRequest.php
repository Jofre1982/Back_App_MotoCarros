<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/{ride}/start — ver openapi.yaml.
 */
class StartRideRequest extends FormRequest
{
    /**
     * El viaje llega resuelto por el binding implícito de la ruta, igual que
     * en `CancelRideRequest`: un id inexistente sale como 404 antes de que se
     * evalúe `authorize()`.
     *
     * Acá el permiso sí depende del viaje concreto —solo lo inicia el
     * conductor asignado—, a diferencia de `AcceptRideRequest`, donde
     * cualquier conductor podía intentarlo.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('start', $this->ride()) ?? false;
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
            $this->rejectRideNotAccepted(...),
        ];
    }

    /**
     * Que el viaje no esté en `accepted` no es un problema de permisos —el
     * conductor sigue siendo el asignado— sino de en qué punto del ciclo de
     * vida está: por eso es 422 y no 403, y por eso vive acá y no en
     * `RidePolicy::start()`. Mismo criterio que `rejectAlreadyAccepted()` en
     * `CancelRideRequest`, con el error bajo la clave `ride`, que tampoco es
     * un campo de la entrada.
     *
     * El mensaje no dice en qué estado está el viaje: quien lo pide es el
     * conductor asignado, así que no hay filtración posible, pero el estado
     * concreto es ruido para una app que lo único que puede hacer es refrescar
     * el viaje y volver a mostrar los botones que correspondan.
     */
    private function rejectRideNotAccepted(Validator $validator): void
    {
        if ($this->ride()->status !== RideStatus::Accepted) {
            $validator->errors()->add(
                'ride',
                'Solo se puede iniciar un viaje que esté aceptado.',
            );
        }
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }
}
