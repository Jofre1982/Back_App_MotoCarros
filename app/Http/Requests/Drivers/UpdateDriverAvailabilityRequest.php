<?php

declare(strict_types=1);

namespace App\Http\Requests\Drivers;

use App\DTOs\Coordinates;
use App\DTOs\DriverAvailabilityUpdate;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entrada de PATCH /api/v1/me/availability — ver openapi.yaml.
 */
class UpdateDriverAvailabilityRequest extends FormRequest
{
    private ?DriverProfile $driverProfile = null;

    /**
     * El rol se comprueba primero y sin tocar el perfil, mismo criterio que
     * `UpdateVehicleRequest::authorize()`: a un pasajero le corresponde 403 y
     * no 404, porque operar disponibilidad de conductor no es algo de su rol
     * y un 404 le sugeriría que registrando un perfil podría seguir.
     *
     * Un conductor sin perfil creado sí llega a `driverProfile()`, que
     * responde 404: el recurso no existe, y eso pesa más que la forma del
     * body.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->isDriver()) {
            return false;
        }

        return $user->can('updateAvailability', $this->driverProfile());
    }

    /**
     * El perfil de la cuenta autenticada, o 404 si todavía no existe.
     *
     * Se resuelve por la relación y nunca por un id de la entrada, mismo
     * criterio que `UpdateVehicleRequest::vehicle()`: es lo que hace
     * estructuralmente imposible apuntar este endpoint al perfil de otro
     * conductor.
     */
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
     * Normaliza `is_available` a un booleano real ANTES de `required_if` en
     * `rules()`. Laravel solo compara `required_if:campo,true|false` sin
     * ambigüedad cuando el valor del otro campo ya es un booleano de PHP
     * (`parseDependentRuleParameters()` recién ahí convierte los parámetros
     * `'true'`/`'false'` de la regla); con `1`/`"1"` la comparación es
     * estricta y nunca calza. Se deja intacto si no vino: `required` en
     * `rules()` es quien tiene que rechazarlo, no este hook.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_available')) {
            $this->merge(['is_available' => $this->boolean('is_available')]);
        }
    }

    /**
     * `latitude`/`longitude` son obligatorias al marcarse disponible: sin
     * ubicación conocida, el conductor no entraría en ninguna búsqueda por
     * radio (ver `EloquentNearbyDriverFinder`) y la disponibilidad no
     * serviría de nada. Al marcarse no disponible son opcionales: apagar el
     * servicio no debería exigir mandar una posición.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_available' => ['required', 'boolean'],
            'latitude' => ['required_if:is_available,true', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:is_available,true', 'numeric', 'between:-180,180'],
        ];
    }

    public function toUpdate(): DriverAvailabilityUpdate
    {
        $tieneUbicacion = $this->has('latitude') && $this->has('longitude');

        return new DriverAvailabilityUpdate(
            isAvailable: $this->boolean('is_available'),
            location: $tieneUbicacion
                ? new Coordinates($this->float('latitude'), $this->float('longitude'))
                : null,
        );
    }
}
