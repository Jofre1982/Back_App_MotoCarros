<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/{ride}/rate-driver — ver openapi.yaml.
 */
class RateDriverRequest extends FormRequest
{
    /**
     * El viaje llega resuelto por el binding implícito de la ruta, igual que
     * en `CompleteRideRequest`: un id inexistente sale como 404 antes de que
     * se evalúe `authorize()`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('rateDriver', $this->ride()) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectRideNotCompleted(...),
            $this->rejectRideAlreadyRated(...),
        ];
    }

    /**
     * Que el viaje no esté `completed` no es un problema de permisos —el
     * pasajero sigue siendo el dueño— sino de en qué punto del ciclo de vida
     * está: por eso es 422 y no 403, mismo criterio que
     * `rejectRideNotInProgress()` en `CompleteRideRequest`.
     */
    private function rejectRideNotCompleted(Validator $validator): void
    {
        if ($this->ride()->status !== RideStatus::Completed) {
            $validator->errors()->add(
                'ride',
                'Solo se puede calificar al conductor de un viaje completado.',
            );
        }
    }

    /**
     * Una sola calificación del conductor por viaje (historia #27, criterio
     * de aceptación #2). El índice único de `ride_ratings` respalda esto
     * mismo a nivel de base si dos llamadas llegaran a la vez.
     */
    private function rejectRideAlreadyRated(Validator $validator): void
    {
        if ($this->ride()->driverRating !== null) {
            $validator->errors()->add(
                'ride',
                'Ya existe una calificación del conductor para este viaje.',
            );
        }
    }

    public function score(): int
    {
        return $this->integer('score');
    }

    public function comment(): ?string
    {
        return $this->string('comment')->value() ?: null;
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }
}
