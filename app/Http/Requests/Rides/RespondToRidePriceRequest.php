<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/{ride}/price-response — ver openapi.yaml.
 */
class RespondToRidePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('respondToPrice', $this->ride()) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:accept,reject'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [$this->rejectRideNotAwaitingPrice(...)];
    }

    private function rejectRideNotAwaitingPrice(Validator $validator): void
    {
        $ride = $this->ride();
        // Hay oferta sin responder: viaje solo recogida, aceptado, con
        // conductor y precio, y todavía sin confirmar.
        $awaiting = $ride->isPickupOnly()
            && $ride->status === RideStatus::Accepted
            && $ride->agreed_price !== null
            && ! $ride->isPriceConfirmed();

        if (! $awaiting) {
            $validator->errors()->add('ride', 'Este viaje no está esperando que respondas un precio.');
        }
    }

    public function acceptsPrice(): bool
    {
        return $this->input('decision') === 'accept';
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }
}
