<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa al conductor asignado a un viaje según el schema `RideDriver` de
 * openapi.yaml.
 *
 * Es deliberadamente más chico que {@see UserResource}: ahí el que mira es el
 * dueño de la cuenta, acá es la contraparte del viaje. Va lo mínimo para saber
 * a quién se está esperando; el `email`, el `phone` y el `role` no tienen nada
 * que hacer del otro lado, y los datos de la moto (placa, tipo) no entran
 * todavía porque ninguna historia los pidió.
 *
 * Sí publica el `id`, al revés que `DriverProfileResource`: es el id de `User`
 * con el que el cliente ya nombra a la persona en el canal `driver.{id}`.
 *
 * @property-read User $resource
 */
class RideDriverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}
