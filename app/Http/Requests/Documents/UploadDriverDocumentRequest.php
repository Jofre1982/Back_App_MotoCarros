<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\DTOs\DriverDocumentUpload;
use App\Enums\DocumentType;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Entrada de POST /api/v1/me/documents — ver openapi.yaml.
 */
class UploadDriverDocumentRequest extends FormRequest
{
    private ?DriverProfile $driverProfile = null;

    /**
     * Basta con el rol: `DriverDocumentPolicy::upload()` no depende de una
     * fila (no hay id en la ruta), mismo criterio que
     * `VehiclePolicy::create()` en `RegisterVehicleRequest`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('upload', DriverDocument::class) ?? false;
    }

    /**
     * El perfil de la cuenta autenticada. Todo conductor tiene uno desde el
     * registro (`RegisterDriverAction` crea cuenta y perfil juntos), pero se
     * resuelve por la relación y no se asume, mismo criterio defensivo que
     * `UpdateDriverAvailabilityRequest::driverProfile()`.
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

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(DocumentType::class)],
            // 5 MB alcanza de sobra para una foto de documento; mimes ya
            // descarta cualquier cosa que no sea imagen o PDF antes de
            // guardarlo en disco.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function toUpload(): DriverDocumentUpload
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return new DriverDocumentUpload(
            type: DocumentType::from($this->string('type')->toString()),
            file: $file,
        );
    }
}
