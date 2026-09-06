<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\DTOs\SiteFareUpdate;
use App\Enums\PricingUnit;
use App\Enums\VehicleType;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Entrada de PUT /api/v1/admin/sites/{site}/fare — ver openapi.yaml.
 */
class SetSiteFareRequest extends FormRequest
{
    /**
     * `{site}` resuelve por binding implícito de la ruta, igual que
     * `{document}` en `ApproveDriverDocumentRequest`: un id inexistente sale
     * como 404 antes de que se evalúe `authorize()`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->site()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'vehicle_type' => ['required', new Enum(VehicleType::class)],
            'pricing_unit' => ['required', new Enum(PricingUnit::class)],
            // Enteros en COP, nunca float — ver .claude/STANDARDS.md.
            'day_price' => ['required', 'integer', 'min:0'],
            'night_price' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function site(): Site
    {
        /** @var Site */
        return $this->route('site');
    }

    public function toUpdate(): SiteFareUpdate
    {
        return new SiteFareUpdate(
            vehicleType: VehicleType::from($this->string('vehicle_type')->toString()),
            pricingUnit: PricingUnit::from($this->string('pricing_unit')->toString()),
            dayPrice: $this->integer('day_price'),
            nightPrice: $this->filled('night_price') ? $this->integer('night_price') : null,
        );
    }
}
