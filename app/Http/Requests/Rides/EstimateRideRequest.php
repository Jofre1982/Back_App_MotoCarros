<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\DTOs\Coordinates;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/estimate — ver openapi.yaml.
 */
class EstimateRideRequest extends FormRequest
{
    /**
     * Cualquier cuenta autenticada puede pedir un estimado: no hay ninguna
     * decisión de negocio que resolver acá, la ruta ya exige `auth:api`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Los límites de latitud/longitud repiten los que ya valida el
     * constructor de `Coordinates`, a propósito: acá el rechazo llega como un
     * 422 por campo, no como la `InvalidArgumentException` que lanzaría el DTO
     * si se lo dejara validar a él.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'array'],
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination' => ['required', 'array'],
            'destination.latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function origin(): Coordinates
    {
        return new Coordinates(
            latitude: $this->float('origin.latitude'),
            longitude: $this->float('origin.longitude'),
        );
    }

    public function destination(): Coordinates
    {
        return new Coordinates(
            latitude: $this->float('destination.latitude'),
            longitude: $this->float('destination.longitude'),
        );
    }
}
