<?php

declare(strict_types=1);

namespace App\Events\Realtime;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento de prueba del tiempo real (issue #5).
 *
 * No es un evento de negocio: existe para confirmar de punta a punta que la
 * infraestructura funciona —un cliente se autoriza en el canal privado, se
 * suscribe y recibe el mensaje— antes de que las historias de tracking y
 * solicitudes cercanas dependan de ella. Los eventos reales llegan con esas
 * historias y se disparan al final de su Action (ver .claude/STANDARDS.md).
 *
 * Es `ShouldBroadcastNow` y no `ShouldBroadcast` porque una prueba de concepto
 * que queda esperando a que alguien levante un worker de colas no prueba nada.
 * Los eventos de negocio sí usarán la cola: publicar es una llamada HTTP al
 * servidor de Reverb y no tiene por qué retrasar la respuesta al cliente.
 */
final class RealtimePingSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $driverId,
        public readonly string $message,
    ) {}

    /**
     * Va al canal del conductor —y no a uno de prueba— para que la prueba de
     * concepto ejercite la autorización real de `driver.{id}`.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("driver.{$this->driverId}");
    }

    /**
     * Nombre con el que el cliente escucha el evento. Explícito para no atar
     * la app móvil al FQCN de una clase de PHP.
     */
    public function broadcastAs(): string
    {
        return 'realtime.ping';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
