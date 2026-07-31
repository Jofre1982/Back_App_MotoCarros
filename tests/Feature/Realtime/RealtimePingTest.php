<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Actions\Realtime\SendRealtimePingAction;
use App\Events\Realtime\RealtimePingSent;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Prueba de concepto del tiempo real de punta a punta (issue #5): la Action
 * dispara el evento, el evento llega al broadcaster con el canal, el nombre y
 * el payload que espera el cliente suscrito.
 *
 * El broadcaster real se reemplaza por uno que anota lo que recibiría el
 * servidor de Reverb. Es el último punto del backend antes de la red: si el
 * evento llega bien hasta acá, lo que sigue es Reverb entregándolo a quien se
 * autorizó en el canal (ver BroadcastAuthEndpointTest).
 */
class RealtimePingTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_ping_llega_al_broadcaster_en_el_canal_privado_del_conductor(): void
    {
        $grabador = $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();

        app(SendRealtimePingAction::class)->handle($conductor, 'hola conductor');

        $this->assertCount(1, $grabador->emitidos);
        [$canales, $evento, $payload] = $grabador->emitidos[0];

        $this->assertSame(["private-driver.{$conductor->getKey()}"], $canales);
        $this->assertSame('realtime.ping', $evento);
        $this->assertSame('hola conductor', $payload['message']);
    }

    public function test_no_se_manda_un_ping_a_un_pasajero_porque_nadie_lo_escucharia(): void
    {
        $grabador = $this->grabarBroadcasts();
        $pasajero = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(SendRealtimePingAction::class)->handle($pasajero, 'hola');
        } finally {
            $this->assertSame([], $grabador->emitidos);
        }
    }

    public function test_el_evento_declara_el_canal_y_el_nombre_que_escucha_el_cliente(): void
    {
        $evento = new RealtimePingSent(driverId: 42, message: 'hola');

        $this->assertSame('private-driver.42', $evento->broadcastOn()->name);
        $this->assertSame('realtime.ping', $evento->broadcastAs());
        $this->assertSame(['message' => 'hola'], $evento->broadcastWith());
    }

    public function test_el_comando_de_prueba_reporta_el_canal_al_que_publico(): void
    {
        $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();

        $this->artisan('realtime:ping', ['driver' => $conductor->getKey(), 'message' => 'hola'])
            ->assertSuccessful();
    }

    public function test_el_comando_falla_si_el_usuario_no_existe(): void
    {
        $this->artisan('realtime:ping', ['driver' => 999])->assertFailed();
    }

    public function test_el_comando_falla_si_el_usuario_no_es_conductor(): void
    {
        $pasajero = User::factory()->create();

        $this->artisan('realtime:ping', ['driver' => $pasajero->getKey()])->assertFailed();
    }

    /**
     * Registra una conexión de broadcasting que, en vez de hablar con Reverb,
     * anota lo que se le pidió publicar.
     */
    private function grabarBroadcasts(): object
    {
        $grabador = new class implements Broadcaster
        {
            /** @var list<array{0: list<string>, 1: string, 2: array<string, mixed>}> */
            public array $emitidos = [];

            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                $this->emitidos[] = [array_map(strval(...), $channels), $event, $payload];
            }
        };

        Broadcast::extend('recording', fn (): Broadcaster => $grabador);
        Config::set('broadcasting.connections.recording', ['driver' => 'recording']);
        Config::set('broadcasting.default', 'recording');

        return $grabador;
    }
}
