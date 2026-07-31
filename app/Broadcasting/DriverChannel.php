<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

/**
 * Canal privado `driver.{driverId}`: la línea directa con un conductor.
 *
 * Por acá le llegan las solicitudes de viaje cercanas y todo lo que es para él
 * y no para el viaje. Es el canal más sensible del sistema —quien lo escucha
 * ve dónde se están pidiendo viajes en tiempo real—, así que la regla es
 * estrecha a propósito: cada conductor escucha su propio canal y nada más.
 *
 * El `{driverId}` es el id del `User` con rol conductor, no el de
 * `driver_profiles`. La API ya identifica a todo el mundo por el id de `User`
 * (es el `sub` del JWT) y tener dos numeraciones para la misma persona es una
 * fuente de errores de autorización.
 */
final readonly class DriverChannel
{
    /**
     * @param  string  $driverId  Llega como string desde el nombre del canal.
     */
    public function join(User $user, string $driverId): bool
    {
        if ((string) $user->getKey() !== $driverId) {
            return false;
        }

        // El perfil es lo que habilita a recibir viajes (ver la decisión de #1
        // en .claude/STANDARDS.md): un usuario con rol conductor pero sin
        // perfil creado todavía no es un conductor operativo, así que tampoco
        // tiene por qué escuchar solicitudes.
        return $user->isDriver() && $user->driverProfile()->exists();
    }
}
