<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ChecksAdSlots;
use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Entrada de POST /api/v1/admin/ads — ver openapi.yaml. `multipart/form-data`
 * porque puede llevar la imagen del negocio.
 */
class CreateAdRequest extends FormRequest
{
    use ChecksAdSlots;

    public const string WHATSAPP_PATTERN = '/^\+?[0-9 ]{7,20}$/';

    public function authorize(): bool
    {
        return $this->user()?->can('create', Ad::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:60'],
            'message' => ['required', 'string', 'max:140'],
            'whatsapp' => ['nullable', 'string', 'regex:'.self::WHATSAPP_PATTERN],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty()) {
                    $this->rejectFullSlots($validator, $this->string('starts_on')->toString(), $this->string('ends_on')->toString());
                }
            },
        ];
    }

    /**
     * @return array{business_name: string, message: string, whatsapp: string|null, starts_on: string, ends_on: string}
     */
    public function toAttributes(): array
    {
        $whatsapp = $this->string('whatsapp')->trim()->toString();

        return [
            'business_name' => $this->string('business_name')->trim()->toString(),
            'message' => $this->string('message')->trim()->toString(),
            'whatsapp' => $whatsapp === '' ? null : $whatsapp,
            'starts_on' => $this->string('starts_on')->toString(),
            'ends_on' => $this->string('ends_on')->toString(),
        ];
    }

    public function uploadedImage(): ?UploadedFile
    {
        $image = $this->file('image');

        return $image instanceof UploadedFile ? $image : null;
    }
}
