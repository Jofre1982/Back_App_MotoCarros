<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\DTOs\Coordinates;
use App\DTOs\RideRequest;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides — ver openapi.yaml.
 */
class CreateRideRequest extends FormRequest
{
    /**
     * Solicitar un viaje es una operación del rol pasajero: lo decide
     * `RidePolicy`, no un `isPassenger()` suelto acá.
     *
     * Va en el Form Request y no en el controller para que el 403 se resuelva
     * **antes** que la validación, igual que en el alta de vehículo: al revés,
     * una cuenta de conductor recibiría un 422 detallándole qué forma tiene que
     * tener la entrada de un endpoint que de todos modos no puede usar.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ride::class) ?? false;
    }

    /**
     * Los límites de latitud/longitud repiten los que ya valida el constructor
     * de `Coordinates`, igual que en la estimación: acá el rechazo llega como un
     * 422 por campo y no como la `InvalidArgumentException` del DTO.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'array'],
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination' => ['required', 'array'],
            'destination.latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Las dos reglas que no dependen de un campo suelto.
     *
     * Ambas corren antes de que el controller invoque al proveedor de mapas, que
     * se paga por consulta: un viaje que se va a rechazar no debería gastar una.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectRepeatedPoint(...),
            $this->rejectSecondActiveRide(...),
        ];
    }

    /**
     * Un viaje que empieza y termina en el mismo punto no es un viaje: el
     * proveedor de mapas devolvería un trayecto de cero metros y el pasajero
     * pagaría la tarifa mínima por quedarse donde está.
     *
     * La comparación es por igualdad exacta y no por cercanía: "demasiado cerca
     * para pedir un viaje" es una decisión de producto con un umbral que alguien
     * tiene que fijar, y no es lo que pide la historia.
     */
    private function rejectRepeatedPoint(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $sameLatitude = $this->float('origin.latitude') === $this->float('destination.latitude');
        $sameLongitude = $this->float('origin.longitude') === $this->float('destination.longitude');

        if ($sameLatitude && $sameLongitude) {
            $validator->errors()->add(
                'destination',
                'El destino no puede ser el mismo punto que el origen.',
            );
        }
    }

    /**
     * El "un viaje activo por pasajero" se comprueba acá y no con una regla
     * sobre un campo, por el mismo motivo que el segundo vehículo de un
     * conductor: no lo decide nada de lo que el cliente manda sino el estado de
     * su cuenta. Por eso el error viaja bajo la clave `ride`, que no es un campo
     * de la entrada.
     *
     * Es 422 y no 409 por la misma razón que allá: para el cliente móvil es el
     * mismo camino que cualquier otro rechazo del formulario.
     *
     * Lo que garantiza la regla cuando dos solicitudes llegan a la vez no es
     * esta consulta sino el índice único de `rides` (ver la migración); esto
     * existe para poder responder 422 en vez de 500 en el caso normal.
     */
    private function rejectSecondActiveRide(Validator $validator): void
    {
        $passenger = $this->user();

        if (! $passenger instanceof User) {
            return;
        }

        $hasActiveRide = Ride::query()
            ->where('passenger_id', $passenger->getKey())
            ->whereIn('status', RideStatus::active())
            ->exists();

        if ($hasActiveRide) {
            $validator->errors()->add(
                'ride',
                'Ya tienes un viaje en curso; termínalo o cancélalo antes de solicitar otro.',
            );
        }
    }

    /**
     * Traduce la entrada validada al DTO que consume la Action, que no conoce
     * HTTP (ver .claude/STANDARDS.md).
     */
    public function toRideRequest(): RideRequest
    {
        return new RideRequest(
            origin: new Coordinates(
                latitude: $this->float('origin.latitude'),
                longitude: $this->float('origin.longitude'),
            ),
            destination: new Coordinates(
                latitude: $this->float('destination.latitude'),
                longitude: $this->float('destination.longitude'),
            ),
        );
    }
}
