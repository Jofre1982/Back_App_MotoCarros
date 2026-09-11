<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\VehicleType;
use App\Models\SiteFare;
use Illuminate\Contracts\Validation\Validator;

/**
 * Reglas compartidas entre `CreateRideRequest` y `EstimateRideRequest`
 * (historia #87): ambas piden lo mismo (un sitio de destino y cuántos
 * pasajeros) y tienen que rechazar exactamente los mismos casos, para que la
 * tarifa estimada y la que se paga al pedir el viaje de verdad salgan del
 * mismo lugar (ver `CalculateSiteFareAction`).
 */
trait ValidatesRideDestination
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function destinationRules(): array
    {
        return [
            'destination_site_id' => ['required', 'integer', 'exists:sites,id'],
            // La capacidad de un motocarro: 1 a 3 personas.
            'passenger_count' => ['required', 'integer', 'between:1,3'],
        ];
    }

    /**
     * Que el sitio no tenga precio de pasajero (Motocarro) definido no es un
     * problema del pasajero: es que al admin todavía le falta cargarlo (ver
     * historia #85). Por eso viaja bajo `destination_site_id`, que sí es un
     * campo de la entrada —a diferencia de otros errores de negocio de esta
     * app que viajan bajo una clave sintética.
     */
    protected function rejectSiteWithoutMotocarroFare(Validator $validator): void
    {
        if ($validator->errors()->has('destination_site_id')) {
            return;
        }

        $hasFare = SiteFare::query()
            ->where('site_id', $this->destinationSiteId())
            ->where('vehicle_type', VehicleType::Motocarro)
            ->exists();

        if (! $hasFare) {
            $validator->errors()->add(
                'destination_site_id',
                'Ese sitio todavía no tiene un precio de pasajero definido.',
            );
        }
    }

    public function destinationSiteId(): int
    {
        return $this->integer('destination_site_id');
    }

    public function passengerCount(): int
    {
        return $this->integer('passenger_count');
    }
}
