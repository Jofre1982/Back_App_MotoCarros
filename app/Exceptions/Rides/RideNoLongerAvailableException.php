<?php

declare(strict_types=1);

namespace App\Exceptions\Rides;

use RuntimeException;

/**
 * El viaje que se intentó aceptar ya no está en `requested` (historia #18):
 * otro conductor lo aceptó primero, o cambió de estado por otro motivo.
 *
 * Se traduce a 409 en bootstrap/app.php y no a 422: no es un problema de forma
 * de la entrada ni del estado de la cuenta del conductor —eso ya lo filtró
 * `AcceptRideRequest`— sino de que el recurso cambió entre que el conductor lo
 * vio disponible y que la petición llegó al servidor.
 *
 * No conoce HTTP, igual que el resto de excepciones de dominio del proyecto
 * (ver `InvalidCredentialsException`): la traducción a código de estado vive
 * en el exception handler, no acá.
 */
final class RideNoLongerAvailableException extends RuntimeException
{
    public const MESSAGE = 'Este viaje ya no está disponible.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
