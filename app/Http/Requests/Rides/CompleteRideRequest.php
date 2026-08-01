<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/{ride}/complete — ver openapi.yaml.
 */
class CompleteRideRequest extends FormRequest
{
    /**
     * El viaje llega resuelto por el binding implícito de la ruta, igual que
     * en `StartRideRequest`: un id inexistente sale como 404 antes de que se
     * evalúe `authorize()`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('complete', $this->ride()) ?? false;
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
            $this->rejectRideNotInProgress(...),
        ];
    }

    /**
     * Que el viaje no esté en `in_progress` no es un problema de permisos
     * —el conductor sigue siendo el asignado— sino de en qué punto del ciclo
     * de vida está: por eso es 422 y no 403, mismo criterio que
     * `rejectRideNotAccepted()` en `StartRideRequest`, con el error bajo la
     * clave `ride`, que tampoco es un campo de la entrada.
     */
    private function rejectRideNotInProgress(Validator $validator): void
    {
        if ($this->ride()->status !== RideStatus::InProgress) {
            $validator->errors()->add(
                'ride',
                'Solo se puede completar un viaje que esté en curso.',
            );
        }
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }
}
