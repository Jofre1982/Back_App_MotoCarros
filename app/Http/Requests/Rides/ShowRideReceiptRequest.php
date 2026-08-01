<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/rides/{ride}/receipt — ver openapi.yaml.
 */
class ShowRideReceiptRequest extends FormRequest
{
    /**
     * El viaje llega resuelto por el binding implícito de la ruta, igual que
     * en `ShowRideRequest`: un id inexistente sale como 404 antes de que se
     * evalúe `authorize()`. Que un viaje ajeno responda 403 y no 404 es a
     * propósito, mismo criterio que `ShowRideRequest`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewReceipt', $this->ride()) ?? false;
    }

    /**
     * Un GET no trae cuerpo, y el único dato que decide esta operación es el
     * viaje de la ruta.
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
            $this->rejectRideWithoutReceipt(...),
        ];
    }

    /**
     * El recibo solo existe una vez que el viaje terminó y se procesó su
     * cobro: acá no es un problema de permisos —el pasajero sigue siendo el
     * dueño— sino de que todavía no hay nada que mostrar, por eso es 422 y
     * no 403 ni 404. Mismo criterio que `rejectRideNotAccepted()` en
     * `StartRideRequest`, con el error bajo la clave `ride`.
     */
    private function rejectRideWithoutReceipt(Validator $validator): void
    {
        $ride = $this->ride();

        if ($ride->status !== RideStatus::Completed || $ride->payment === null) {
            $validator->errors()->add(
                'ride',
                'El recibo todavía no está disponible para este viaje.',
            );
        }
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }
}
