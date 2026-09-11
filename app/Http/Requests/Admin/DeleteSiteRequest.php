<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de DELETE /api/v1/admin/sites/{site} — ver openapi.yaml.
 */
class DeleteSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->site()) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function site(): Site
    {
        /** @var Site */
        return $this->route('site');
    }
}
