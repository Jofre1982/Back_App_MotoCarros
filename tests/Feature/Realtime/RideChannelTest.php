<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Broadcasting\RideChannel;
use App\Models\User;
use App\Services\Realtime\PendingRideParticipants;
use App\Services\Realtime\RideParticipants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autorización del canal privado `ride.{rideId}` (issue #5).
 *
 * La regla que se prueba acá es la definitiva —solo el pasajero y el conductor
 * del viaje lo escuchan— aunque el modelo `Ride` todavía no exista: de dónde
 * salen esos ids está detrás de RideParticipants, y acá se reemplaza por una
 * implementación de prueba.
 */
class RideChannelTest extends TestCase
{
    use RefreshDatabase;

    private const RIDE_ID = 7;

    public function test_el_pasajero_del_viaje_entra_al_canal(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();

        $channel = $this->channelConParticipantes([(int) $pasajero->getKey(), (int) $conductor->getKey()]);

        $this->assertTrue($channel->join($pasajero, (string) self::RIDE_ID));
    }

    public function test_el_conductor_asignado_entra_al_canal(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();

        $channel = $this->channelConParticipantes([(int) $pasajero->getKey(), (int) $conductor->getKey()]);

        $this->assertTrue($channel->join($conductor, (string) self::RIDE_ID));
    }

    public function test_un_tercero_no_entra_al_canal_del_viaje(): void
    {
        $pasajero = User::factory()->create();
        $curioso = User::factory()->create();

        $channel = $this->channelConParticipantes([(int) $pasajero->getKey()]);

        $this->assertFalse($channel->join($curioso, (string) self::RIDE_ID));
    }

    public function test_un_viaje_que_no_existe_no_deja_entrar_a_nadie(): void
    {
        $usuario = User::factory()->create();

        $channel = $this->channelConParticipantes([(int) $usuario->getKey()]);

        $this->assertFalse($channel->join($usuario, '999'));
    }

    /**
     * El id llega del nombre del canal, que lo escribe el cliente: nada que no
     * sea un entero se convierte silenciosamente a uno.
     */
    public function test_un_id_de_viaje_que_no_es_un_entero_no_entra(): void
    {
        $usuario = User::factory()->create();

        $channel = $this->channelConParticipantes([(int) $usuario->getKey()]);

        foreach ([self::RIDE_ID.'abc', ' '.self::RIDE_ID, '-'.self::RIDE_ID, ''] as $idInvalido) {
            $this->assertFalse(
                $channel->join($usuario, (string) $idInvalido),
                "El id de canal '{$idInvalido}' no debería autorizar."
            );
        }
    }

    /**
     * Mientras no exista el modelo `Ride` (historia #15) el canal tiene que
     * fallar cerrado: sin esto, un canal "provisionalmente abierto" expondría
     * la ubicación en vivo de conductores y pasajeros.
     */
    public function test_la_implementacion_registrada_hoy_deniega_todo(): void
    {
        $usuario = User::factory()->create();

        $this->assertInstanceOf(PendingRideParticipants::class, app(RideParticipants::class));

        $this->assertFalse(
            app(RideChannel::class)->join($usuario, (string) self::RIDE_ID),
        );
    }

    /**
     * @param  list<int>  $participantes
     */
    private function channelConParticipantes(array $participantes): RideChannel
    {
        return new RideChannel(new class(self::RIDE_ID, $participantes) implements RideParticipants
        {
            /**
             * @param  list<int>  $participantes
             */
            public function __construct(
                private readonly int $viaje,
                private readonly array $participantes,
            ) {}

            /**
             * @return list<int>
             */
            public function forRide(int $rideId): array
            {
                return $rideId === $this->viaje ? $this->participantes : [];
            }
        });
    }
}
