<?php

declare(strict_types=1);

namespace App\Exceptions\Errands;

use RuntimeException;

/**
 * El mandado que se intentó aceptar ya no está en `requested` (historia
 * #92): otro conductor lo aceptó primero.
 *
 * Se traduce a 409 en bootstrap/app.php, mismo criterio que
 * `RideNoLongerAvailableException`: no es un problema de la entrada ni del
 * rol de quien pide, sino de que el recurso cambió entre que se vio
 * disponible y que la petición llegó al servidor.
 */
final class ErrandNoLongerAvailableException extends RuntimeException
{
    public const MESSAGE = 'Este mandado ya no está disponible.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
