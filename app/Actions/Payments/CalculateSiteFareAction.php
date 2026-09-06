<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\DTOs\FareBreakdown;
use App\DTOs\FareSchedule;
use App\Enums\PricingUnit;
use App\Enums\VehicleType;
use App\Models\Site;
use App\Models\SiteFare;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Calcula lo que cuesta un viaje a partir del precio fijo de un sitio
 * (historia #87), en vez de la distancia/duración recorridas.
 *
 * Es la única fuente de verdad del monto para el flujo de viajes desde #87:
 * la estimación que se le muestra al pasajero antes de pedir el viaje y el
 * cobro al terminarlo salen de acá con los mismos parámetros — mismo
 * criterio que `CalculateFareAction`, que este Action reemplaza en ese flujo
 * (`CalculateFareAction` sigue existiendo, sin uso, por si se retoma tarifa
 * por distancia más adelante).
 *
 * Devuelve el mismo `FareBreakdown` que el motor anterior, con el precio
 * completo en `base` y el resto de los conceptos en cero: así
 * `ChargeRideAction`, `Payment` y el recibo (historias #25/#26) siguen
 * funcionando sin cambios, aunque ya no haya un desglose real que mostrar.
 */
final readonly class CalculateSiteFareAction
{
    public function __construct(private FareSchedule $schedule) {}

    /**
     * @throws ModelNotFoundException si el sitio no tiene un precio definido
     *                                para ese tipo de vehículo. El Form Request
     *                                ya debería haber rechazado esto antes de
     *                                invocar la Action.
     */
    public function handle(Site $site, VehicleType $vehicleType, int $passengerCount): FareBreakdown
    {
        /** @var SiteFare $siteFare */
        $siteFare = SiteFare::query()
            ->where('site_id', $site->getKey())
            ->where('vehicle_type', $vehicleType)
            ->firstOrFail();

        $unitPrice = $siteFare->priceAt(now());
        $total = $siteFare->pricing_unit === PricingUnit::PerPerson
            ? $unitPrice * $passengerCount
            : $unitPrice;

        return new FareBreakdown(
            currency: $this->schedule->currency,
            base: $total,
            distance: 0,
            time: 0,
            waiting: 0,
            subtotal: $total,
            total: $total,
            minimumApplied: false,
        );
    }
}
