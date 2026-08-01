<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Enums\RideStatus;
use App\Events\Realtime\RideStatusChanged;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RecordsBroadcasts;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Los cambios de estado del viaje que salen por el canal privado `ride.{id}`
 * (historia #21).
 *
 * Es la otra mitad de lo que el pasajero escucha mientras sigue su viaje: la
 * ubicación del conductor la publica la historia #20, y las transiciones del
 * ciclo de vida —que alguien aceptó, que el viaje arrancó— salen de las
 * Actions que las producen.
 *
 * Se prueba desde el endpoint y no solo desde la Action porque lo que importa
 * es lo que termina saliendo hacia el cliente (canal, nombre y payload), que
 * es lo que el evento decide al serializarse.
 */
class RideStatusChangedTest extends TestCase
{
    use RecordsBroadcasts, RefreshDatabase;

    public function test_aceptar_un_viaje_publica_el_cambio_de_estado_en_el_canal_del_viaje(): void
    {
        $grabador = $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/accept")
            ->assertOk();

        [$canales, , $payload] = $this->unicoCambioDeEstado($grabador);

        $this->assertSame(["private-ride.{$viaje->id}"], $canales);
        $this->assertSame(RideStatus::Accepted->value, $payload['status']);
        $this->assertSame($conductor->id, $payload['driver_id']);
    }

    public function test_iniciar_un_viaje_publica_el_cambio_de_estado_en_el_canal_del_viaje(): void
    {
        $grabador = $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/start")
            ->assertOk();

        [$canales, , $payload] = $this->unicoCambioDeEstado($grabador);

        $this->assertSame(["private-ride.{$viaje->id}"], $canales);
        $this->assertSame(RideStatus::InProgress->value, $payload['status']);
        $this->assertSame($conductor->id, $payload['driver_id']);
    }

    /**
     * Perder la carrera por un viaje ya aceptado no cambia ningún estado, así
     * que tampoco anuncia uno: el pasajero recibiría dos "te aceptaron" por un
     * único conductor asignado.
     */
    public function test_un_intento_de_aceptar_que_llega_tarde_no_publica_nada(): void
    {
        $grabador = $this->grabarBroadcasts();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::Accepted,
            'driver_id' => User::factory()->driver()->create()->id,
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson("/api/v1/rides/{$viaje->id}/accept")
            ->assertConflict();

        $this->assertSame([], $grabador->porEvento('status.changed'));
    }

    public function test_el_evento_declara_el_canal_y_el_nombre_que_escucha_el_cliente(): void
    {
        $evento = new RideStatusChanged(rideId: 7, status: RideStatus::InProgress, driverId: 42);

        $this->assertSame('private-ride.7', $evento->broadcastOn()->name);
        $this->assertSame('status.changed', $evento->broadcastAs());
        $this->assertSame(
            ['status' => 'in_progress', 'driver_id' => 42],
            $evento->broadcastWith(),
        );
    }

    /**
     * El único `status.changed` de la request. Se filtra por evento porque
     * aceptar puede publicar además el retiro de la solicitud hacia los otros
     * conductores cercanos (historia #17), que acá no viene al caso; que sea
     * exactamente uno sí importa, y es lo que se afirma.
     *
     * @return array{0: list<string>, 1: string, 2: array<string, mixed>}
     */
    private function unicoCambioDeEstado(object $grabador): array
    {
        $cambios = $grabador->porEvento('status.changed');

        $this->assertCount(1, $cambios);

        return $cambios[0];
    }
}
