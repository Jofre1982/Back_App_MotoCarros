<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Errand;
use App\Models\Ride;
use App\Models\Site;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectSiteInUse(...),
        ];
    }

    /**
     * `rides.destination_site_id` y `errands.destination_site_id` son
     * `restrictOnDelete`: sin esta regla el borrado de un sitio ya usado
     * escalaba a un 500 por la llave foránea. 422 con la clave `site` (no es
     * un campo de la entrada), mismo criterio que
     * `ApproveDriverDocumentRequest::rejectDocumentNotPending()`.
     */
    private function rejectSiteInUse(Validator $validator): void
    {
        $siteId = $this->site()->id;
        $inUse = Ride::query()->where('destination_site_id', $siteId)->exists()
            || Errand::query()->where('destination_site_id', $siteId)->exists();

        if ($inUse) {
            $validator->errors()->add(
                'site',
                'Este destino ya tiene viajes o mandados y no se puede borrar. Puedes cambiarle el precio.',
            );
        }
    }

    public function site(): Site
    {
        /** @var Site */
        return $this->route('site');
    }
}
