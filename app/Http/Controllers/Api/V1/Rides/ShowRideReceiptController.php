<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rides;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rides\ShowRideReceiptRequest;
use App\Http\Resources\RideReceiptResource;
use App\Models\Ride;

/**
 * GET /api/v1/rides/{ride}/receipt
 *
 * Devuelve el desglose del cobro de un viaje completado (historia #26): lo
 * que el pasajero pagó y por qué concepto.
 *
 * No hay Action detrás, mismo criterio que `ShowRideController`: leer un
 * viaje y su pago ya resueltos no decide ni cambia nada, y envolverlo en una
 * Action sería un pasamanos. La Policy —`RidePolicy::viewReceipt()`— y que
 * el viaje tenga recibo disponible los resuelve `ShowRideReceiptRequest`.
 */
class ShowRideReceiptController extends Controller
{
    public function __invoke(ShowRideReceiptRequest $request, Ride $ride): RideReceiptResource
    {
        return new RideReceiptResource($ride->loadMissing('payment'));
    }
}
