<?php

declare(strict_types=1);

namespace App\Http\Requests\Errands;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/errands/{errand}/price-response — ver openapi.yaml.
 */
class RespondToErrandPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('respondToPrice', $this->errand()) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:accept,reject'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [$this->rejectErrandNotAwaitingPrice(...)];
    }

    private function rejectErrandNotAwaitingPrice(Validator $validator): void
    {
        $errand = $this->errand();
        $awaiting = $errand->status === ErrandStatus::Accepted && ! $errand->isPriceConfirmed();

        if (! $awaiting) {
            $validator->errors()->add('errand', 'Este mandado no está esperando que respondas un precio.');
        }
    }

    public function acceptsPrice(): bool
    {
        return $this->input('decision') === 'accept';
    }

    public function errand(): Errand
    {
        /** @var Errand */
        return $this->route('errand');
    }
}
