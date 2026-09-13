<?php

declare(strict_types=1);

namespace App\Http\Requests\Drivers;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entrada de PATCH /api/v1/me/availability/errands — ver openapi.yaml.
 */
class UpdateDriverErrandAvailabilityRequest extends FormRequest
{
    private ?DriverProfile $driverProfile = null;

    /**
     * Mismo criterio que `UpdateDriverAvailabilityRequest`: el rol se
     * comprueba primero y sin tocar el perfil, para que a un pasajero le
     * corresponda 403 y no 404. Reutiliza `DriverProfilePolicy::updateAvailability()`
     * — es la misma pregunta ("¿es esta cuenta la dueña de este perfil?"),
     * sin importar cuál de las dos disponibilidades se esté tocando.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->isDriver()) {
            return false;
        }

        return $user->can('updateAvailability', $this->driverProfile());
    }

    public function driverProfile(): DriverProfile
    {
        if ($this->driverProfile instanceof DriverProfile) {
            return $this->driverProfile;
        }

        $user = $this->user();
        $profile = $user instanceof User ? $user->driverProfile : null;

        if (! $profile instanceof DriverProfile) {
            throw new NotFoundHttpException(
                'No tienes un perfil de conductor; regístrate como conductor antes de marcarte disponible.',
            );
        }

        return $this->driverProfile = $profile;
    }

    /**
     * A diferencia de `UpdateDriverAvailabilityRequest`, no hay ubicación
     * que exigir: no hay matching por cercanía para mandados (historia #92),
     * así que marcarse disponible no depende de publicar una posición.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_available_for_errands' => ['required', 'boolean'],
        ];
    }

    public function isAvailable(): bool
    {
        return $this->boolean('is_available_for_errands');
    }
}
