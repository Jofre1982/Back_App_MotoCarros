<?php

declare(strict_types=1);

namespace App\Actions\Realtime;

use App\Events\Realtime\RealtimePingSent;
use App\Models\User;
use InvalidArgumentException;

/**
 * Manda un mensaje de prueba al canal privado de un conductor (issue #5).
 *
 * Es la Action de prueba que exige el issue: existe para verificar la
 * infraestructura de tiempo real, no para el negocio. Sirve igual desde el
 * comando `realtime:ping`, un test o un tinker, porque no conoce HTTP — el
 * mismo patrón que van a seguir las Actions que disparen eventos de verdad
 * (ver .claude/STANDARDS.md).
 */
final readonly class SendRealtimePingAction
{
    /**
     * @throws InvalidArgumentException si el usuario no es un conductor, porque
     *                                  entonces nadie está autorizado a escuchar
     *                                  ese canal y el mensaje se perdería en
     *                                  silencio.
     */
    public function handle(User $driver, string $message): void
    {
        if (! $driver->isDriver()) {
            throw new InvalidArgumentException(
                "El usuario {$driver->getKey()} no es un conductor: nadie escucha su canal."
            );
        }

        RealtimePingSent::dispatch((int) $driver->getKey(), $message);
    }
}
