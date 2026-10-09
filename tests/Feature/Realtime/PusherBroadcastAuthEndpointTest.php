<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

/**
 * La misma suite de POST /api/v1/broadcasting/auth, contra el broadcaster de
 * Pusher.
 *
 * Producción (cPanel) publica por Pusher porque ahí no puede correr Reverb
 * (ver .claude/STANDARDS.md, "Tiempo real"). Los dos hablan el mismo protocolo
 * y firman igual, pero son drivers distintos de Laravel: este test es el que
 * asegura que la conexión `pusher` de config/broadcasting.php está bien armada
 * y que los canales y sus reglas valen igual con ella. Firma con las
 * credenciales `PUSHER_APP_*` de phpunit.xml, que repiten a propósito los
 * valores de `REVERB_APP_*` para que las constantes de la clase padre sirvan.
 */
class PusherBroadcastAuthEndpointTest extends BroadcastAuthEndpointTest
{
    protected function conexion(): string
    {
        return 'pusher';
    }
}
