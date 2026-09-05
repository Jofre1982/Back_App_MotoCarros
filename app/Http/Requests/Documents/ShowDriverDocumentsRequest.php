<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entrada de GET /api/v1/me/documents — ver openapi.yaml.
 */
class ShowDriverDocumentsRequest extends FormRequest
{
    private ?DriverProfile $driverProfile = null;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', DriverDocument::class) ?? false;
    }

    /**
     * Mismo criterio defensivo que `UploadDriverDocumentRequest::driverProfile()`.
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
                'No tienes un perfil de conductor.',
            );
        }

        return $this->driverProfile = $profile;
    }
}
