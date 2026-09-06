<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\DriverDocument;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/admin/documents/{document}/file — ver openapi.yaml.
 */
class ShowDriverDocumentFileRequest extends FormRequest
{
    /**
     * `{document}` resuelve por binding implícito de la ruta, igual que
     * `ApproveDriverDocumentRequest`: un id inexistente sale como 404 antes
     * de que se evalúe `authorize()`.
     *
     * Misma autorización que aprobar/rechazar (`review()`): ver el archivo
     * es parte del mismo flujo de revisión, no una operación aparte con
     * reglas propias.
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

    public function document(): DriverDocument
    {
        /** @var DriverDocument */
        return $this->route('document');
    }
}
