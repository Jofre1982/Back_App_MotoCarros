<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Realtime\SendRealtimePingAction;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Prueba de concepto del tiempo real (issue #5), en el mismo espíritu que
 * `maps:estimate` para el proveedor de mapas.
 *
 * Con `php artisan reverb:start` corriendo y un cliente suscrito a
 * `private-driver.{id}`, esto confirma la cadena completa: autorización del
 * canal, publicación al servidor y entrega al suscriptor.
 *
 *   php artisan realtime:ping 7 "hola desde el backend"
 */
final class SendRealtimePingCommand extends Command
{
    protected $signature = 'realtime:ping
                            {driver : Id del usuario conductor dueño del canal}
                            {message=Ping de prueba desde MotoYa : Texto a enviar}';

    protected $description = 'Envía un mensaje de prueba al canal privado de un conductor para verificar el broadcasting.';

    public function handle(SendRealtimePingAction $action): int
    {
        $driver = User::query()->find($this->argument('driver'));

        if ($driver === null) {
            $this->components->error("No existe un usuario con id {$this->argument('driver')}.");

            return self::FAILURE;
        }

        try {
            $action->handle($driver, (string) $this->argument('message'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Conexión', (string) config('broadcasting.default'));
        $this->components->twoColumnDetail('Canal', "private-driver.{$driver->getKey()}");
        $this->components->twoColumnDetail('Evento', 'realtime.ping');

        return self::SUCCESS;
    }
}
