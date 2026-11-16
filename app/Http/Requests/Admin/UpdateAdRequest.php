<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ChecksAdSlots;
use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Entrada de PATCH /api/v1/admin/ads/{ad} — ver openapi.yaml. Solo cambia los
 * campos que vengan; `is_paused` pausa o reanuda. La imagen se cambia aparte
 * (POST /admin/ads/{ad}/image).
 */
class UpdateAdRequest extends FormRequest
{
    use ChecksAdSlots;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->ad()) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'string', 'max:60'],
            'message' => ['sometimes', 'string', 'max:140'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'regex:'.CreateAdRequest::WHATSAPP_PATTERN],
            'starts_on' => ['sometimes', 'date_format:Y-m-d'],
            'ends_on' => ['sometimes', 'date_format:Y-m-d'],
            'is_paused' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if ($this->endsOn() < $this->startsOn()) {
                    $validator->errors()->add('ends_on', 'La fecha final no puede ser antes de la fecha de inicio.');

                    return;
                }
                if (! $this->willBePaused()) {
                    $this->rejectFullSlots($validator, $this->startsOn(), $this->endsOn(), $this->ad()->id);
                }
            },
        ];
    }

    public function ad(): Ad
    {
        /** @var Ad */
        return $this->route('ad');
    }

    /**
     * @return array{business_name?: string, message?: string, whatsapp?: string|null, starts_on?: string, ends_on?: string, is_paused?: bool}
     */
    public function toAttributes(): array
    {
        $attributes = [];
        foreach (['business_name', 'message', 'starts_on', 'ends_on'] as $field) {
            if ($this->has($field)) {
                $attributes[$field] = $this->string($field)->trim()->toString();
            }
        }
        if ($this->has('whatsapp')) {
            $whatsapp = $this->string('whatsapp')->trim()->toString();
            $attributes['whatsapp'] = $whatsapp === '' ? null : $whatsapp;
        }
        if ($this->has('is_paused')) {
            $attributes['is_paused'] = $this->boolean('is_paused');
        }

        return $attributes;
    }

    private function startsOn(): string
    {
        return $this->has('starts_on') ? $this->string('starts_on')->toString() : $this->ad()->starts_on->toDateString();
    }

    private function endsOn(): string
    {
        return $this->has('ends_on') ? $this->string('ends_on')->toString() : $this->ad()->ends_on->toDateString();
    }

    private function willBePaused(): bool
    {
        return $this->has('is_paused') ? $this->boolean('is_paused') : $this->ad()->is_paused;
    }
}
