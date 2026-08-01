<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Events\Realtime\DriverLocationUpdated;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/rides/{id}/location — ver openapi.yaml.
 *
 * Historia #20: el conductor asignado publica su posición mientras el viaje
 * está en curso, para que el pasajero la siga en tiempo real (historia #21)
 * por el canal privado `ride.{id}`.
 */
class ShareRideLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_conductor_asignado_comparte_su_ubicacion_en_el_viaje_en_curso(): void
    {
        $grabador = $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();
        $viaje = $this->viajeEnCursoDe($conductor);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje), ['latitude' => 4.706, 'longitude' => -74.068])
            ->assertNoContent();

        $this->assertCount(1, $grabador->emitidos);
        [$canales, $evento, $payload] = $grabador->emitidos[0];

        $this->assertSame(["private-ride.{$viaje->id}"], $canales);
        $this->assertSame('location.updated', $evento);
        $this->assertSame($conductor->id, $payload['driver_id']);
        $this->assertSame(4.706, $payload['latitude']);
        $this->assertSame(-74.068, $payload['longitude']);
    }

    public function test_el_evento_declara_el_canal_y_el_nombre_que_escucha_el_cliente(): void
    {
        $evento = new DriverLocationUpdated(rideId: 7, driverId: 42, latitude: 4.706, longitude: -74.068);

        $this->assertSame('private-ride.7', $evento->broadcastOn()->name);
        $this->assertSame('location.updated', $evento->broadcastAs());
        $this->assertSame(
            ['driver_id' => 42, 'latitude' => 4.706, 'longitude' => -74.068],
            $evento->broadcastWith(),
        );
    }

    #[DataProvider('coordenadasFueraDeRango')]
    public function test_rechaza_coordenadas_fuera_de_rango(array $coordenadas, string $campo): void
    {
        $grabador = $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();
        $viaje = $this->viajeEnCursoDe($conductor);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje), $coordenadas)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campo);

        $this->assertSame([], $grabador->emitidos);
    }

    /**
     * @return array<string, array{array<string, float>, string}>
     */
    public static function coordenadasFueraDeRango(): array
    {
        return [
            'latitud excede 90' => [['latitude' => 90.5, 'longitude' => -74.068], 'latitude'],
            'latitud excede -90' => [['latitude' => -90.5, 'longitude' => -74.068], 'latitude'],
            'longitud excede 180' => [['latitude' => 4.706, 'longitude' => 180.5], 'longitude'],
            'longitud excede -180' => [['latitude' => 4.706, 'longitude' => -180.5], 'longitude'],
            'falta latitud' => [['longitude' => -74.068], 'latitude'],
            'falta longitud' => [['latitude' => 4.706], 'longitude'],
        ];
    }

    public function test_rechaza_a_un_conductor_que_no_es_el_asignado(): void
    {
        $grabador = $this->grabarBroadcasts();
        $asignado = User::factory()->driver()->create();
        $otroConductor = User::factory()->driver()->create();
        $viaje = $this->viajeEnCursoDe($asignado);

        $this->withToken(JWTAuth::fromUser($otroConductor))
            ->postJson($this->uri($viaje), ['latitude' => 4.706, 'longitude' => -74.068])
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertSame([], $grabador->emitidos);
    }

    public function test_rechaza_al_pasajero_del_viaje(): void
    {
        $grabador = $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje), ['latitude' => 4.706, 'longitude' => -74.068])
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertSame([], $grabador->emitidos);
    }

    #[DataProvider('estadosQueNoEstanEnCurso')]
    public function test_rechaza_un_viaje_que_no_esta_en_curso(RideStatus $estado): void
    {
        $grabador = $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => $estado,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje), ['latitude' => 4.706, 'longitude' => -74.068])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');

        $this->assertSame([], $grabador->emitidos);
    }

    /**
     * @return array<string, array{RideStatus}>
     */
    public static function estadosQueNoEstanEnCurso(): array
    {
        return [
            RideStatus::Requested->value => [RideStatus::Requested],
            RideStatus::Accepted->value => [RideStatus::Accepted],
            RideStatus::Completed->value => [RideStatus::Completed],
            RideStatus::Cancelled->value => [RideStatus::Cancelled],
        ];
    }

    public function test_responde_404_cuando_el_viaje_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson('/api/v1/rides/999999/location', ['latitude' => 4.706, 'longitude' => -74.068])
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $viaje = $this->viajeEnCursoDe(User::factory()->driver()->create());

        $this->postJson($this->uri($viaje), ['latitude' => 4.706, 'longitude' => -74.068])
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    private function viajeEnCursoDe(User $conductor): Ride
    {
        return Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
        ]);
    }

    private function uri(Ride $viaje): string
    {
        return "/api/v1/rides/{$viaje->id}/location";
    }

    /**
     * Registra una conexión de broadcasting que, en vez de hablar con Reverb,
     * anota lo que se le pidió publicar (mismo patrón que RealtimePingTest).
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
