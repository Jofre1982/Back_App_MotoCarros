<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Entrada de POST /api/v1/me/avatar — ver openapi.yaml. Cualquier cuenta
 * autenticada sube su propia foto: no hay recurso ajeno que autorizar.
 */
class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function photo(): UploadedFile
    {
        $photo = $this->file('photo');
        if (! $photo instanceof UploadedFile) {
            throw new RuntimeException('UploadAvatarRequest sin foto validada.');
        }

        return $photo;
    }
}
