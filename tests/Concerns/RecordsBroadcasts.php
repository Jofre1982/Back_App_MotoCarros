<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;

/**
 * Registra una conexión de broadcasting que, en vez de hablar con Reverb,
 * anota lo que se le pidió publicar.
 *
 * Se prefiere sobre `Event::fake()` porque lo que interesa probar no es que la
 * Action haya despachado una clase, sino lo que efectivamente sale hacia el
 * cliente: en qué canal, con qué nombre de evento y con qué payload. Con el
 * evento falseado, `broadcastOn()`/`broadcastAs()`/`broadcastWith()` —que es
 * justo el contrato que consume la app móvil— nunca llegan a ejecutarse.
 *
 * Vive en un trait porque lo comparten los tests de todo lo que hoy viaja por
 * los canales: el ping de prueba (#5), la solicitud y su retiro hacia los
 * conductores cercanos (#17), la ubicación del conductor (#20) y los cambios
 * de estado del viaje (#21).
 *
 * La conexión queda registrada al arrancar el test, sin que cada caso tenga
 * que pedirla: alcanza con que la clase use el trait. Es a propósito —una
 * request que publique algo sin que el test lo esperara intentaría hablar con
 * un Reverb real y fallaría por timeout de red, que es un síntoma bastante
 * malo para diagnosticar. Los casos que además quieran mirar lo publicado
 * piden el grabador con `grabarBroadcasts()`.
 */
trait RecordsBroadcasts
{
    private ?object $grabadorDeBroadcasts = null;

    /**
     * Lo llama Laravel al arrancar cada test, por convención de nombre
     * (`setUp` + el nombre del trait; ver `TestCase::setUpTraits()`).
     */
    protected function setUpRecordsBroadcasts(): void
    {
        $this->grabarBroadcasts();
    }

    /**
     * Devuelve el grabador registrado como conexión por defecto, el mismo
     * durante todo el test. Su propiedad pública `$emitidos` acumula un
     * `[canales, evento, payload]` por publicación, en orden.
     */
    protected function grabarBroadcasts(): object
    {
        if ($this->grabadorDeBroadcasts !== null) {
            return $this->grabadorDeBroadcasts;
        }

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

            /**
             * Solo lo publicado bajo un nombre de evento.
             *
             * Una misma request puede publicar varias cosas distintas —aceptar
             * un viaje le avisa al pasajero y además retira la solicitud de los
             * otros conductores—, así que un test que mira una de ellas filtra
             * en vez de dar por hecho que es la única.
             *
             * @return list<array{0: list<string>, 1: string, 2: array<string, mixed>}>
             */
            public function porEvento(string $evento): array
            {
                return array_values(array_filter(
                    $this->emitidos,
                    fn (array $emitido): bool => $emitido[1] === $evento,
                ));
            }
        };

        Broadcast::extend('recording', fn (): Broadcaster => $grabador);
        Config::set('broadcasting.connections.recording', ['driver' => 'recording']);
        Config::set('broadcasting.default', 'recording');

        return $this->grabadorDeBroadcasts = $grabador;
    }
}
