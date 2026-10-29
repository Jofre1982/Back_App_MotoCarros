<?php

declare(strict_types=1);

namespace App\Http\Requests\Errands;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/errands/{errand}/cancel — ver openapi.yaml.
 */
class CancelErrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->errand()) ?? false;
    }

    /**
     * @return array<string, mixed>
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
        return [$this->rejectFinishedErrand(...)];
    }

    private function rejectFinishedErrand(Validator $validator): void
    {
        if (in_array($this->errand()->status, [ErrandStatus::Completed, ErrandStatus::Cancelled], true)) {
            $validator->errors()->add('errand', 'Este mandado ya terminó; no se puede cancelar.');
        }
    }

    public function errand(): Errand
    {
        /** @var Errand */
        return $this->route('errand');
    }
}
