<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa la cuenta autenticada vista por su dueño, según el schema `Profile`
 * de openapi.yaml.
 *
 * Es `UserResource` más lo que depende del rol, y por eso se compone sobre él
 * en vez de repetir sus campos: `User` es el schema que devuelven el login y los
 * registros, y si las dos listas divergieran, la app móvil recibiría una cuenta
 * distinta según por dónde entró.
 *
 * @property-read User $resource
 */
class ProfileResource extends JsonResource
{
    /**
     * La clave `driver_profile` **se omite entera** cuando no aplica, en vez de
     * viajar en `null`: en una cuenta de pasajero no es un dato que falte, y un
     * `null` dejaría al cliente sin poder distinguirla de un conductor que
     * todavía no cargó su licencia.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $datos = (new UserResource($this->resource))->toArray($request);

        $perfilDeConductor = $this->resource->isDriver()
            ? $this->resource->driverProfile
            : null;

        if ($perfilDeConductor !== null) {
            $datos['driver_profile'] = new DriverProfileResource($perfilDeConductor);
        }

        return $datos;
    }
}
