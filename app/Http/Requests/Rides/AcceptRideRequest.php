<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/{ride}/accept — ver openapi.yaml.
 */
class AcceptRideRequest extends FormRequest
{
    /**
     * El viaje llega resuelto por el binding implícito de la ruta, igual que
     * en `CancelRideRequest`: un id inexistente sale como 404 antes de que se
     * evalúe `authorize()`.
     *
     * Que el permiso sea del rol conductor lo decide `RidePolicy`, no depende
     * del viaje de la ruta: cualquier conductor puede intentar aceptar
     * cualquier viaje `requested`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('accept', Ride::class) ?? false;
    }

    /**
     * No hay campos en el cuerpo: todo lo que decide esta operación es el
     * viaje de la ruta y quién lo pide.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // Historia #95: un viaje solo recogida se acepta registrando el
        // precio acordado con el pasajero, con la misma regla que al aceptar
        // un mandado. Uno de catálogo no lo necesita (y si llega, se ignora).
        if (! $this->ride()->isPickupOnly()) {
            return [];
        }

        return [
            'agreed_price' => ['required', 'integer', 'min:1'],
        ];
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }

    public function agreedPrice(): ?int
    {
        return $this->ride()->isPickupOnly() ? $this->integer('agreed_price') : null;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectOwnRide(...),
            $this->rejectDriverWithActiveRide(...),
            $this->rejectDriverWithActivePassengerRide(...),
        ];
    }

    /**
     * "Un viaje activo por conductor" se comprueba acá y no con una regla de
     * campo, mismo criterio que `CreateRideRequest::rejectSecondActiveRide()`:
     * no lo decide nada de lo que el cliente manda sino el estado de su
     * cuenta. Por eso el error viaja bajo la clave `ride`, que tampoco es un
     * campo de la entrada.
     *
     * Lo que garantiza la regla cuando dos aceptaciones del mismo conductor
     * llegan a la vez no es esta consulta sino el índice único de
     * `active_driver_id` (ver la migración); esto existe para poder responder
     * 422 en vez de 500 en el caso normal.
     *
     * Que el viaje de la ruta ya no esté disponible (lo aceptó otro conductor
     * primero) no se decide acá: es 409 y lo resuelve `AcceptRideAction` bajo
     * lock, porque el estado puede cambiar entre que se valida esta petición y
     * que se ejecuta.
     */
    private function rejectDriverWithActiveRide(Validator $validator): void
    {
        $driver = $this->user();

        if (! $driver instanceof User) {
            return;
        }

        $hasActiveRide = Ride::query()
            ->where('driver_id', $driver->getKey())
            ->whereIn('status', RideStatus::active())
            ->exists();

        if ($hasActiveRide) {
            $validator->errors()->add(
                'ride',
                'Ya tienes un viaje activo; termínalo o cancélalo antes de aceptar otro.',
            );
        }
    }

    /**
     * Historia #99: una cuenta puede ser pasajero y conductor, pero no puede
     * aceptar el viaje que ella misma pidió.
     */
    private function rejectOwnRide(Validator $validator): void
    {
        if ($this->ride()->passenger_id === $this->user()?->getKey()) {
            $validator->errors()->add('ride', 'No puedes aceptar un viaje que pediste tú.');
        }
    }

    /**
     * Historia #99: quien tiene un viaje activo como pasajero no puede, a la
     * vez, llevar a otro como conductor.
     */
    private function rejectDriverWithActivePassengerRide(Validator $validator): void
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return;
        }

        $hasActiveRideAsPassenger = Ride::query()
            ->where('passenger_id', $user->getKey())
            ->whereIn('status', RideStatus::active())
            ->exists();

        if ($hasActiveRideAsPassenger) {
            $validator->errors()->add(
                'ride',
                'Tienes un viaje en curso como pasajero; termínalo o cancélalo antes de aceptar uno como conductor.',
            );
        }
    }
}
