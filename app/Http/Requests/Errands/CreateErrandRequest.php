<?php

declare(strict_types=1);

namespace App\Http\Requests\Errands;

use App\DTOs\Coordinates;
use App\DTOs\ErrandRequest;
use App\Enums\VehicleType;
use App\Models\Errand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Enum;

/**
 * Entrada de POST /api/v1/errands — ver openapi.yaml.
 */
class CreateErrandRequest extends FormRequest
{
    /**
     * Pedir un mandado es del rol pasajero: lo decide `ErrandPolicy`, no un
     * `isPassenger()` suelto acá, mismo criterio que `CreateRideRequest`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Errand::class) ?? false;
    }

    /**
     * `destination_site_id` solo exige que el sitio exista: a diferencia de
     * `CreateRideRequest`, un mandado no tiene tarifa fija, así que no hace
     * falta que el sitio tenga un `SiteFare` cargado (historia #92 — el
     * precio se negocia por fuera).
     *
     * `photo` es opcional y comparte límites con `UploadDriverDocumentRequest`
     * (solo imagen, 5 MB): es una foto del ítem a recoger, no un documento,
     * así que no acepta PDF.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:500'],
            'origin' => ['required', 'array'],
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination_site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'destination_text' => ['nullable', 'string', 'max:255'],
            'vehicle_type' => ['nullable', new Enum(VehicleType::class)],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function toErrandRequest(): ErrandRequest
    {
        /** @var UploadedFile|null $photo */
        $photo = $this->file('photo');

        return new ErrandRequest(
            description: $this->string('description')->toString(),
            origin: new Coordinates(
                latitude: $this->float('origin.latitude'),
                longitude: $this->float('origin.longitude'),
            ),
            destinationSiteId: $this->filled('destination_site_id') ? $this->integer('destination_site_id') : null,
            photo: $photo,
            vehicleType: $this->filled('vehicle_type')
                ? VehicleType::from($this->string('vehicle_type')->toString())
                : VehicleType::Motocarro,
            destinationText: $this->filled('destination_text') ? $this->string('destination_text')->trim()->toString() : null,
        );
    }
}
