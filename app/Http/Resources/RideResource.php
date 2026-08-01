<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un viaje según el schema `Ride` de openapi.yaml.
 *
 * Publica el `id` —al revés que el vehículo y el perfil del conductor— porque
 * el viaje sí se direcciona por él: es el `{rideId}` del canal privado
 * `ride.{rideId}` y el que van a llevar los endpoints de aceptar, iniciar y
 * completar. No publica el `passenger_id`: el viaje se solicita siempre desde la
 * cuenta que manda el token. Sí publica al conductor asignado, que es la única
 * de las dos partes que el otro lado no conoce de antemano (historia #21).
 *
 * @property-read Ride $resource
 */
class RideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status->value,
            'origin' => [
                'latitude' => $this->resource->origin_latitude,
                'longitude' => $this->resource->origin_longitude,
            ],
            'destination' => [
                'latitude' => $this->resource->destination_latitude,
                'longitude' => $this->resource->destination_longitude,
            ],
            'distance_meters' => $this->resource->estimated_distance_meters,
            'duration_seconds' => $this->resource->estimated_duration_seconds,
            'currency' => $this->resource->currency,
            'estimated_fare' => $this->resource->estimated_fare,
            // Presente siempre, aunque valga `null`, por el mismo motivo que
            // `started_at`: el contrato lo declara obligatorio y nullable para
            // que el cliente no distinga "todavía no hay conductor" de "esta
            // respuesta no lo trae" (historia #21).
            //
            // La ubicación del conductor no está acá y no es un olvido: no se
            // persiste, viaja solo por el evento `location.updated` del canal
            // `ride.{id}` (historia #20). Que `driver` sea `null` es
            // justamente lo que dice que todavía no hay nadie publicándola.
            'driver' => $this->resource->driver === null
                ? null
                : new RideDriverResource($this->resource->driver),
            // ISO-8601 explícito en vez del formato por defecto de Eloquent:
            // el contrato publica `format: date-time` y el default trae
            // microsegundos que nadie usa acá.
            'requested_at' => $this->resource->created_at?->toIso8601String(),
            // Presente siempre, aunque valga `null`: el contrato lo declara
            // obligatorio y nullable justamente para que el cliente no tenga
            // que distinguir "el viaje todavía no empezó" de "esta respuesta
            // no trae el campo" (historia #19).
            'started_at' => $this->resource->started_at?->toIso8601String(),
            // Mismo criterio que `started_at`, para "el viaje todavía no
            // terminó" (historia #24).
            'completed_at' => $this->resource->completed_at?->toIso8601String(),
            // El cobro final recalculado al completar el viaje, distinto de
            // `estimated_fare`. `null` hasta que el viaje se completa; mismo
            // criterio de siempre presente que el resto de los campos que
            // nacen a mitad del ciclo de vida.
            'final_fare' => $this->resource->final_fare,
            // El procesamiento del cobro (historia #25), distinto de
            // `final_fare`: ese es el monto a cobrar, esto es si el cobro se
            // hizo. `null` hasta que `CompleteRideAction` dispara
            // `ChargeRideAction`, mismo criterio que el resto de los campos
            // que nacen a mitad del ciclo de vida. No repite `amount` ni
            // `currency`: ya están en `final_fare` y `currency` arriba, y el
            // pago no puede valer algo distinto.
            'payment' => $this->resource->payment === null
                ? null
                : ['status' => $this->resource->payment->status->value],
        ];
    }
}
