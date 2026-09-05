<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\DocumentStatus;
use App\Models\DriverDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Entrada de GET /api/v1/admin/documents — ver openapi.yaml.
 */
class ListDriverDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewAny', DriverDocument::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', new Enum(DocumentStatus::class)],
        ];
    }

    /**
     * `pending` por defecto: es la cola de trabajo del administrador, y sin
     * filtro explícito es lo único que le importa consultar.
     */
    public function status(): DocumentStatus
    {
        return $this->filled('status')
            ? DocumentStatus::from($this->string('status')->toString())
            : DocumentStatus::Pending;
    }
}
