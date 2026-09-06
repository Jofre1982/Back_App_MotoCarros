<?php

declare(strict_types=1);

namespace App\Http\Requests\Sites;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/sites — ver openapi.yaml.
 */
class ListSitesRequest extends FormRequest
{
    /**
     * Cualquier cuenta autenticada puede consultar el catálogo: no hay
     * ninguna decisión de negocio que resolver acá, la ruta ya exige
     * `auth:api`. A diferencia de `GET /admin/sites` (solo admin, que además
     * puede crear/editar), esto es de solo lectura para que el pasajero
     * elija destino al pedir un viaje (historia #87).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
