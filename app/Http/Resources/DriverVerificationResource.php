<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DocumentType;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Serializa el estado de verificación de un conductor: el estado general y el
 * detalle de cada uno de los documentos exigidos por `DocumentType::required()`,
 * lo haya subido o no.
 *
 * @property-read DriverProfile $resource
 */
class DriverVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<string, DriverDocument> $subidosPorTipo */
        $subidosPorTipo = $this->resource->documents->keyBy(
            fn (DriverDocument $documento): string => $documento->type->value,
        );

        return [
            'verification_status' => $this->resource->verification_status->value,
            'documents' => collect(DocumentType::required())
                ->map(function (DocumentType $tipo) use ($subidosPorTipo): array {
                    $documento = $subidosPorTipo->get($tipo->value);

                    return [
                        'type' => $tipo->value,
                        'status' => $documento?->status->value,
                        'rejection_reason' => $documento?->rejection_reason,
                        'uploaded_at' => $documento?->created_at?->toISOString(),
                    ];
                })
                ->values(),
        ];
    }
}
