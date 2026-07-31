<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * Las credenciales de inicio de sesión no corresponden a ninguna cuenta.
 *
 * Es **una sola** excepción para los dos motivos posibles —contraseña que no
 * coincide y email sin cuenta— y a propósito no lleva ningún dato que permita
 * distinguirlos: si el motivo viajara hasta acá, tarde o temprano alguien lo
 * renderizaría y el endpoint pasaría a contestar qué emails tienen cuenta.
 *
 * No conoce HTTP: no trae código de estado ni sabe que existe un 401. La
 * traducción a respuesta ocurre en bootstrap/app.php, junto al resto de fallos
 * de autenticación, para que las Actions sigan siendo invocables desde un
 * comando o un job (ver .claude/STANDARDS.md).
 */
final class InvalidCredentialsException extends RuntimeException
{
    /**
     * Mensaje genérico, y el único que el cliente llega a ver.
     */
    public const MESSAGE = 'El email o la contraseña no son correctos.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
