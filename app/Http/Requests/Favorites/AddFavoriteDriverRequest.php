<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorites;

use App\Models\Errand;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Entrada de POST /api/v1/me/favorite-drivers — ver openapi.yaml.
 *
 * Solo se puede guardar a un conductor que ya le prestó un servicio al
 * pasajero: así nadie arma una lista de conductores que no conoce.
 */
class AddFavoriteDriverRequest extends FormRequest
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
            'driver_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectDriverWithoutSharedService(...),
        ];
    }

    private function rejectDriverWithoutSharedService(Validator $validator): void
    {
        if ($validator->errors()->has('driver_id')) {
            return;
        }

        $mine = fn ($query) => $query
            ->where('passenger_id', $this->passenger()->getKey())
            ->where('driver_id', $this->driverId());

        if ($this->driverId() === $this->passenger()->getKey()
            || (! Ride::query()->where($mine)->exists() && ! Errand::query()->where($mine)->exists())) {
            $validator->errors()->add('driver_id', 'Solo puedes guardar como favorito a un conductor que ya te haya llevado.');
        }
    }

    public function passenger(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    public function driverId(): int
    {
        return $this->integer('driver_id');
    }

    public function driver(): User
    {
        return User::query()->findOrFail($this->driverId());
    }
}
