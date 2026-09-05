<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\DocumentStatus;
use App\Models\DriverDocument;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/admin/documents/{document}/approve — ver openapi.yaml.
 */
class ApproveDriverDocumentRequest extends FormRequest
{
    /**
     * `{document}` resuelve por binding implícito de la ruta, igual que
     * `{ride}` en `CompleteRideRequest`: un id inexistente sale como 404
     * antes de que se evalúe `authorize()`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->document()) ?? false;
    }

    /**
     * No hay campos en el cuerpo: todo lo que decide esta operación es el
     * documento de la ruta.
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
            $this->rejectDocumentNotPending(...),
        ];
    }

    /**
     * Que el documento no esté `pending` no es un problema de permisos —el
     * administrador sigue siéndolo— sino de en qué punto de revisión está:
     * por eso es 422 y no 403, mismo criterio que
     * `CompleteRideRequest::rejectRideNotInProgress()`, con el error bajo la
     * clave `document`, que tampoco es un campo de la entrada.
     */
    private function rejectDocumentNotPending(Validator $validator): void
    {
        if ($this->document()->status !== DocumentStatus::Pending) {
            $validator->errors()->add(
                'document',
                'Solo se puede revisar un documento pendiente.',
            );
        }
    }

    public function document(): DriverDocument
    {
        /** @var DriverDocument */
        return $this->route('document');
    }
}
