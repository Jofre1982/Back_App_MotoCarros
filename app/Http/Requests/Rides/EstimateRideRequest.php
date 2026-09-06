<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\DTOs\Coordinates;
use App\Http\Requests\Concerns\ValidatesRideDestination;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de POST /api/v1/rides/estimate — ver openapi.yaml.
 */
class EstimateRideRequest extends FormRequest
{
    use ValidatesRideDestination;

    /**
     * Cualquier cuenta autenticada puede pedir un estimado: no hay ninguna
     * decisión de negocio que resolver acá, la ruta ya exige `auth:api`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El límite de latitud/longitud del origen repite el que ya valida el
     * constructor de `Coordinates`, a propósito: acá el rechazo llega como un
     * 422 por campo, no como la `InvalidArgumentException` que lanzaría el
     * DTO si se lo dejara validar a él. El destino ya no es un punto libre
     * (historia #87): ver `ValidatesRideDestination`.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'array'],
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
            ...$this->destinationRules(),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            $this->rejectSiteWithoutMotocarroFare(...),
        ];
    }

    public function origin(): Coordinates
    {
        return new Coordinates(
            latitude: $this->float('origin.latitude'),
            longitude: $this->float('origin.longitude'),
        );
    }

    public function destinationSite(): Site
    {
        /** @var Site */
        return Site::query()->findOrFail($this->destinationSiteId());
    }
}
