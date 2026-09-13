<?php

declare(strict_types=1);

namespace App\Http\Requests\Errands;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/errands/{errand}/complete — ver openapi.yaml.
 */
class CompleteErrandRequest extends FormRequest
{
    /**
     * El mandado llega resuelto por el binding implícito de la ruta, igual
     * que `CompleteRideRequest`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('complete', $this->errand()) ?? false;
    }

    /**
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
            $this->rejectErrandNotAccepted(...),
        ];
    }

    /**
     * Que el mandado no esté `accepted` no es un problema de permisos —el
     * conductor sigue siendo el asignado— sino de en qué punto del ciclo de
     * vida está: 422 y no 403, mismo criterio que
     * `CompleteRideRequest::rejectRideNotInProgress()`.
     */
    private function rejectErrandNotAccepted(Validator $validator): void
    {
        if ($this->errand()->status !== ErrandStatus::Accepted) {
            $validator->errors()->add(
                'errand',
                'Solo se puede completar un mandado que esté aceptado.',
            );
        }
    }

    public function errand(): Errand
    {
        /** @var Errand */
        return $this->route('errand');
    }
}
