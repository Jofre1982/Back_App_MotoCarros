<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\DTOs\Coordinates;
use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/{ride}/location — ver openapi.yaml.
 */
class ShareRideLocationRequest extends FormRequest
{
    /**
     * El viaje llega resuelto por el binding implícito de la ruta, igual que
     * en `StartRideRequest`: un id inexistente sale como 404 antes de que se
     * evalúe `authorize()`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('shareLocation', $this->ride()) ?? false;
    }

    /**
     * Los límites de latitud/longitud repiten los que ya valida el
     * constructor de `Coordinates`, mismo criterio que `EstimateRideRequest`:
     * acá el rechazo llega como un 422 por campo.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectRideNotInProgress(...),
        ];
    }

    /**
     * Que el viaje no esté `in_progress` no es un problema de permisos —el
     * conductor sigue siendo el asignado— sino de en qué punto del ciclo de
     * vida está: por eso es 422 y no 403, mismo criterio que
     * `rejectRideNotAccepted()` en `StartRideRequest`.
     */
    private function rejectRideNotInProgress(Validator $validator): void
    {
        if ($this->ride()->status !== RideStatus::InProgress) {
            $validator->errors()->add(
                'ride',
                'Solo se puede compartir ubicación en un viaje en curso.',
            );
        }
    }

    public function location(): Coordinates
    {
        return new Coordinates(
            latitude: $this->float('latitude'),
            longitude: $this->float('longitude'),
        );
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }
}
