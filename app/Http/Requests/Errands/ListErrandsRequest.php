<?php

declare(strict_types=1);

namespace App\Http\Requests\Errands;

use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/errands — ver openapi.yaml.
 */
class ListErrandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Errand::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Si el conductor no tiene perfil creado, no está disponible para nada
     * — mismo criterio defensivo que `UpdateDriverAvailabilityRequest`, pero
     * sin 404: acá la ausencia de perfil no es un error, simplemente no
     * ve ningún mandado (ver `ListErrandsController`).
     */
    public function isAvailableForErrands(): bool
    {
        $user = $this->user();
        $profile = $user instanceof User ? $user->driverProfile : null;

        return $profile instanceof DriverProfile && $profile->is_available_for_errands;
    }
}
