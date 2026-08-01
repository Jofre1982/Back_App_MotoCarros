<?php

declare(strict_types=1);

namespace App\Http\Requests\Rides;

use App\Models\Ride;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada de GET /api/v1/rides/{ride} — ver openapi.yaml.
 */
class ShowRideRequest extends FormRequest
{
    /**
     * El viaje llega resuelto por el binding implícito de la ruta, igual que
     * en `StartRideRequest`: un id inexistente sale como 404 antes de que se
     * evalúe `authorize()`.
     *
     * Que un viaje ajeno responda 403 y no 404 es a propósito: los ids son
     * correlativos, así que el 404 tampoco escondería si el viaje existe —lo
     * único que agregaría es ambigüedad para el cliente, que no sabría si
     * reintentar o dejar de pedirlo.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->ride()) ?? false;
    }

    /**
     * Un GET no trae cuerpo, y el único dato que decide esta operación es el
     * viaje de la ruta.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function ride(): Ride
    {
        /** @var Ride */
        return $this->route('ride');
    }
}
