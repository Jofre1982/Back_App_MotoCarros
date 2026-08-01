<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado del cobro de un viaje completado (historia #25).
 *
 * Los valores viajan tal cual a `payments.status` y al campo `status` del
 * schema `Payment` de openapi.yaml, mismo criterio que `RideStatus`.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}
