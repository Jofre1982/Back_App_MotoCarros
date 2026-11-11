<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Entrada de POST /api/v1/admin/ads/{ad}/image — ver openapi.yaml.
 */
class ReplaceAdImageRequest extends FormRequest
{
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
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function ad(): Ad
    {
        /** @var Ad */
        return $this->route('ad');
    }

    public function uploadedImage(): UploadedFile
    {
        $image = $this->file('image');
        if (! $image instanceof UploadedFile) {
            throw new RuntimeException('ReplaceAdImageRequest sin imagen validada.');
        }

        return $image;
    }
}
