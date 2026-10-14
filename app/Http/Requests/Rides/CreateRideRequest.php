<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\DTOs\Coordinates;
use App\DTOs\RideRequest;
use App\Enums\RideMode;
use App\Enums\RideStatus;
use App\Http\Requests\Concerns\ValidatesRideDestination;
use App\Models\Ride;
use App\Models\Site;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrada de POST /api/v1/rides — ver openapi.yaml.
 */
class CreateRideRequest extends FormRequest
{
    use ValidatesRideDestination;

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
     * El límite de latitud/longitud del origen repite el que ya valida el
     * constructor de `Coordinates`, igual que en la estimación: acá el
     * rechazo llega como un 422 por campo y no como la
     * `InvalidArgumentException` del DTO. El destino ya no es un punto libre
     * (historia #87): ver `ValidatesRideDestination`.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'mode' => ['sometimes', 'string', Rule::enum(RideMode::class)],
            'origin' => ['required', 'array'],
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
        ];

        // Viaje solo recogida (historia #95): el destino es texto libre y no
        // hay sitio. El `TrimStrings` global ya convierte "   " en `null`, así
        // que `required` rechaza también el texto hecho solo de espacios.
        if ($this->mode() === RideMode::PickupOnly) {
            return [
                ...$rules,
                'destination_text' => ['required', 'string', 'max:255'],
                'passenger_count' => ['required', 'integer', 'between:1,3'],
            ];
        }

        return [...$rules, ...$this->destinationRules()];
    }

    /**
     * La modalidad pedida, o `Catalog` si no viene (los clientes anteriores a
     * la historia #95 no la mandan) o no es válida — en ese caso la regla
     * `mode` ya responde 422.
     */
    public function mode(): RideMode
    {
        $mode = $this->input('mode');

        return (is_string($mode) ? RideMode::tryFrom($mode) : null) ?? RideMode::Catalog;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        if ($this->mode() === RideMode::PickupOnly) {
            return [$this->rejectSecondActiveRide(...)];
        }

        return [
            $this->rejectSiteWithoutMotocarroFare(...),
            $this->rejectSecondActiveRide(...),
        ];
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

            return;
        }

        // Historia #99: quien está llevando a alguien como conductor no puede
        // pedir un viaje como pasajero hasta terminarlo.
        $hasActiveRideAsDriver = Ride::query()
            ->where('driver_id', $passenger->getKey())
            ->whereIn('status', RideStatus::active())
            ->exists();

        if ($hasActiveRideAsDriver) {
            $validator->errors()->add(
                'ride',
                'Tienes un viaje en curso como conductor; termínalo antes de pedir uno como pasajero.',
            );
        }
    }

    public function destinationSite(): Site
    {
        /** @var Site */
        return Site::query()->findOrFail($this->destinationSiteId());
    }

    /**
     * Traduce la entrada validada al DTO que consume la Action, que no conoce
     * HTTP (ver .claude/STANDARDS.md).
     */
    public function toRideRequest(): RideRequest
    {
        $origin = new Coordinates(
            latitude: $this->float('origin.latitude'),
            longitude: $this->float('origin.longitude'),
        );

        if ($this->mode() === RideMode::PickupOnly) {
            return RideRequest::pickupOnly($origin, $this->string('destination_text')->toString(), $this->passengerCount());
        }

        return new RideRequest(
            origin: $origin,
            destinationSiteId: $this->destinationSiteId(),
            passengerCount: $this->passengerCount(),
        );
    }
}
