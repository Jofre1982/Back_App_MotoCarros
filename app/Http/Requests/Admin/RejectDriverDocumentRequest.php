<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\DocumentStatus;
use App\Models\DriverDocument;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/admin/documents/{document}/reject — ver openapi.yaml.
 */
class RejectDriverDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->document()) ?? false;
    }

    /**
     * `reason` es obligatorio: sin motivo el conductor no sabría qué
     * corregir antes de volver a subir el documento.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
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
     * Mismo criterio que `ApproveDriverDocumentRequest::rejectDocumentNotPending()`.
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

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }
}
