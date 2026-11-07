<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Enums\ErrandStatus;
use App\Enums\RideStatus;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * Entrada de DELETE /api/v1/me — ver openapi.yaml.
 *
 * Pide la contraseña otra vez: borrar la cuenta no se puede deshacer y el
 * celular podría estar en otras manos con la sesión abierta.
 */
class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectWrongPassword(...),
            $this->rejectWithServiceInProgress(...),
        ];
    }

    private function rejectWrongPassword(Validator $validator): void
    {
        if ($validator->errors()->has('password')) {
            return;
        }

        if (! Hash::check((string) $this->input('password'), $this->account()->password)) {
            $validator->errors()->add('password', 'La contraseña no es correcta.');
        }
    }

    /**
     * Con un viaje o pedido sin terminar, la otra persona quedaría
     * esperando a alguien que ya no existe.
     */
    private function rejectWithServiceInProgress(Validator $validator): void
    {
        $id = $this->account()->getKey();
        $mine = fn ($query) => $query->where('passenger_id', $id)->orWhere('driver_id', $id);

        $activeRide = Ride::query()
            ->where($mine)
            ->whereIn('status', [RideStatus::Requested, RideStatus::Accepted, RideStatus::InProgress])
            ->exists();
        $activeErrand = Errand::query()
            ->where($mine)
            ->whereIn('status', [ErrandStatus::Requested, ErrandStatus::Accepted])
            ->exists();

        if ($activeRide || $activeErrand) {
            $validator->errors()->add(
                'account',
                'Tienes un viaje o pedido sin terminar. Termínalo o cancélalo antes de eliminar tu cuenta.',
            );
        }
    }

    public function account(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
