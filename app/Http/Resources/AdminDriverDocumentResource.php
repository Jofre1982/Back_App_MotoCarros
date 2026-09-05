<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

/**
 * Serializa un documento tal como lo ve un administrador (historia técnica
 * #64): a diferencia de `DriverDocumentResource`, sí lleva `id` —es por lo
 * que se lo referencia en `/admin/documents/{document}/approve|reject`— y a
 * qué conductor pertenece.
 *
 * @property-read DriverDocument $resource
 */
class AdminDriverDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $perfil = $this->resource->driverProfile;

        // `driver_profile_id` en `driver_documents` y `user_id` en
        // `driver_profiles` son NOT NULL, así que esto siempre es cierto: la
        // comprobación es para PHPStan, que no puede inferir el modelo
        // relacionado de una propiedad dinámica, no un caso real. El
        // controller siempre precarga `driverProfile.user` antes de construir
        // este recurso.
        if (! $perfil instanceof DriverProfile || ! $perfil->user instanceof User) {
            throw new RuntimeException('DriverDocument sin driver_profile.user asociado.');
        }

        $conductor = $perfil->user;

        return [
            'id' => $this->resource->id,
            'driver' => [
                'id' => $conductor->id,
                'name' => $conductor->name,
            ],
            'type' => $this->resource->type->value,
            'status' => $this->resource->status->value,
            'uploaded_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
