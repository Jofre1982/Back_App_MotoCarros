<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/{ride}/cancel — ver openapi.yaml.
 *
 * Mismo endpoint para el pasajero dueño (historias #16/#22) y para el
 * conductor asignado (historia #23); cuál de los dos puede cancelar y qué
 * hace cada uno lo decide `RidePolicy::cancel()` y `CancelRideAction`, no
 * este Form Request.
 */
class CancelRideRequest extends FormRequest
{
    /**
     * El viaje llega resuelto por el binding implícito de la ruta —el
     * `SubstituteBindings` de la ruta corre antes de que el controller
     * resuelva este Form Request—, así que un id inexistente nunca llega
     * hasta acá: sale como 404 antes de que se evalúe `authorize()`.
     *
     * Que el viaje sea del pasajero dueño o del conductor asignado se
     * resuelve acá y no en el controller, igual que en `CreateRideRequest`,
     * para que el 403 se resuelva antes que la validación de estado.
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
            $this->rejectNotCancellable(...),
        ];
    }

    /**
     * `requested` y `accepted` son cancelables por quien ya tiene permiso
     * según `RidePolicy::cancel()` — el pasajero en cualquiera de los dos
     * (historias #16/#22), el conductor asignado solo en `accepted`, porque
     * sin conductor asignado nunca llega hasta acá autorizado (historia
     * #23). A partir de ahí no es un problema de permisos sino de en qué
     * punto del ciclo de vida está el viaje, por eso es 422 y no 403, y por
     * eso vive acá y no en `RidePolicy::cancel()`. Mismo criterio que "ya
     * tiene un viaje activo" en `CreateRideRequest`, con el error bajo la
     * clave `ride`, que tampoco es un campo de la entrada.
     *
     * El `match` cubre los cinco casos de `RideStatus` en vez de aislar los
     * dos cancelables con un `if` antes: así queda un único punto exhaustivo
     * — si `RideStatus` gana un caso nuevo, esto deja de compilar en vez de
     * fallar en silencio con un mensaje genérico.
     */
    private function rejectNotCancellable(Validator $validator): void
    {
        $message = match ($this->ride()->status) {
            RideStatus::Requested, RideStatus::Accepted => null,
            RideStatus::InProgress => 'El viaje está en curso; no se puede cancelar de esta forma.',
            RideStatus::Completed => 'El viaje ya se completó; no se puede cancelar.',
            RideStatus::Cancelled => 'El viaje ya estaba cancelado.',
        };

        if ($message !== null) {
            $validator->errors()->add('ride', $message);
        }
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }
}
