<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * El proveedor de mapas no pudo entregar una estimación utilizable.
 *
 * Agrupa los tres modos de fallo bajo un mismo tipo para que quien consuma el
 * estimador no tenga que distinguir entre excepciones del cliente HTTP y
 * respuestas 200 con un cuerpo inesperado: para el negocio, en los tres casos
 * no hay tarifa que mostrar.
 */
final class RouteEstimationFailed extends RuntimeException
{
    public static function providerRejectedRequest(string $provider, int $status): self
    {
        return new self("El proveedor de mapas '{$provider}' respondió con HTTP {$status}.");
    }

    public static function providerUnreachable(string $provider, string $reason): self
    {
        return new self("No se pudo contactar al proveedor de mapas '{$provider}': {$reason}");
    }

    public static function noRouteAvailable(string $provider): self
    {
        return new self("El proveedor de mapas '{$provider}' no devolvió ninguna ruta entre las coordenadas dadas.");
    }

    public static function unreadableResponse(string $provider, string $detail): self
    {
        return new self("Respuesta inesperada del proveedor de mapas '{$provider}': {$detail}");
    }
}
