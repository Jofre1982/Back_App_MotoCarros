<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Broadcasting\RideChannel;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use App\Services\Realtime\EloquentRideParticipants;
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
     * Desde la historia #20 la implementación registrada ya resuelve
     * participantes reales contra la base, no solo la de prueba que usan los
     * demás tests de esta clase.
     */
    public function test_la_implementacion_registrada_autoriza_al_pasajero_y_al_conductor_reales(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
        ]);

        $this->assertInstanceOf(EloquentRideParticipants::class, app(RideParticipants::class));

        $channel = app(RideChannel::class);
        $this->assertTrue($channel->join($pasajero, (string) $viaje->id));
        $this->assertTrue($channel->join($conductor, (string) $viaje->id));
        $this->assertFalse($channel->join(User::factory()->create(), (string) $viaje->id));
    }

    /**
     * Criterio de aceptación de la historia #21: un pasajero cualquiera no es
     * un tercero anónimo —tiene su propio viaje andando— y aun así no escucha
     * el de otro. El caso importa aparte del tercero sin viajes porque es el
     * que puede confundirse en el cliente: la app manda el id que tiene a mano
     * y el canal no debe darle nada si ese id no es el suyo.
     */
    public function test_un_pasajero_no_entra_al_canal_de_un_viaje_ajeno(): void
    {
        $ajeno = Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => User::factory()->driver()->create()->id,
        ]);
        $otroPasajero = User::factory()->create();
        Ride::factory()->for($otroPasajero, 'passenger')->create();

        $this->assertFalse(app(RideChannel::class)->join($otroPasajero, (string) $ajeno->id));
    }

    public function test_la_implementacion_registrada_deniega_un_viaje_que_no_existe(): void
    {
        $usuario = User::factory()->create();

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
