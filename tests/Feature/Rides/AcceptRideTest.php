<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\DriverProfile;
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
 * Contrato de POST /api/v1/rides/{id}/accept — ver openapi.yaml.
 *
 * Historia #18: un conductor disponible acepta un viaje que sigue en
 * `requested`. El manejo de la carrera entre dos conductores por el mismo
 * viaje es parte central de la historia, no un detalle.
 */
class AcceptRideTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_conductor_acepta_un_viaje_disponible(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.id', $viaje->id);
        $respuesta->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Accepted->value,
            'driver_id' => $conductor->id,
        ]);
    }

    public function test_rechaza_un_viaje_ya_aceptado_por_otro_conductor(): void
    {
        // El primer conductor ya aceptó el viaje: se deja así por el modelo y
        // no por una request previa, porque el guard JWT (stateless) cachea el
        // usuario resuelto en la instancia del guard durante todo el test —dos
        // requests autenticadas con cuentas distintas en el mismo método
        // reusarían la primera. Ver `RequestRideTest` para el mismo criterio.
        $primerConductor = User::factory()->driver()->create();
        $segundoConductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $primerConductor->id]);

        $this->withToken(JWTAuth::fromUser($segundoConductor))
            ->postJson($this->uri($viaje))
            ->assertStatus(409);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Accepted->value,
            'driver_id' => $primerConductor->id,
        ]);
    }

    #[DataProvider('estadosActivosDelConductor')]
    public function test_rechaza_al_conductor_que_ya_tiene_un_viaje_propio_activo(RideStatus $estado): void
    {
        $conductor = User::factory()->driver()->create();
        Ride::factory()->create(['status' => $estado, 'driver_id' => $conductor->id]);
        $otroViaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($otroViaje))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');

        $this->assertDatabaseHas('rides', [
            'id' => $otroViaje->id,
            'status' => RideStatus::Requested->value,
            'driver_id' => null,
        ]);
    }

    public function test_rechaza_a_un_pasajero_que_intenta_aceptar_un_viaje(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', ['id' => $viaje->id, 'status' => RideStatus::Requested->value]);
    }

    public function test_responde_404_cuando_el_viaje_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson('/api/v1/rides/999999/accept')
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->postJson($this->uri($viaje))
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', ['id' => $viaje->id, 'status' => RideStatus::Requested->value]);
    }

    /**
     * Historia #17: al aceptarse, el viaje deja de estar disponible para los
     * demás conductores cercanos que lo habían recibido.
     */
    public function test_avisa_a_los_demas_conductores_cercanos_que_el_viaje_ya_no_esta_disponible(): void
    {
        $grabador = $this->grabarBroadcasts();
        $viaje = $this->viajeDisponibleEnElOrigen();

        $aceptante = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $aceptante->id]);
        $otroCercano = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $otroCercano->id]);

        $this->withToken(JWTAuth::fromUser($aceptante))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $this->assertCount(1, $grabador->emitidos);
        [$canales, $evento, $payload] = $grabador->emitidos[0];

        $this->assertSame(["private-driver.{$otroCercano->id}"], $canales);
        $this->assertSame('ride.unavailable', $evento);
        $this->assertSame($viaje->id, $payload['ride_id']);
    }

    public function test_no_avisa_al_conductor_que_acepto_el_viaje(): void
    {
        $grabador = $this->grabarBroadcasts();
        $viaje = $this->viajeDisponibleEnElOrigen();

        $aceptante = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $aceptante->id]);

        $this->withToken(JWTAuth::fromUser($aceptante))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $this->assertSame([], $grabador->emitidos);
    }

    public function test_no_avisa_a_conductores_disponibles_fuera_del_radio(): void
    {
        $grabador = $this->grabarBroadcasts();
        $viaje = $this->viajeDisponibleEnElOrigen();

        $aceptante = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $aceptante->id]);
        $lejano = User::factory()->driver()->create();
        DriverProfile::factory()->available(latitude: 4.9, longitude: -74.3)->create(['user_id' => $lejano->id]);

        $this->withToken(JWTAuth::fromUser($aceptante))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $this->assertSame([], $grabador->emitidos);
    }

    public function test_no_dispara_ningun_evento_si_no_queda_ningun_otro_conductor_cerca(): void
    {
        $grabador = $this->grabarBroadcasts();
        $viaje = $this->viajeDisponibleEnElOrigen();

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $this->assertSame([], $grabador->emitidos);
    }

    /**
     * Viaje `requested` con un origen fijo, para poder ubicar conductores
     * cerca o lejos de forma determinística (`Ride::factory()` por defecto
     * sortea coordenadas al azar dentro de Bogotá).
     */
    private function viajeDisponibleEnElOrigen(): Ride
    {
        return Ride::factory()->create([
            'status' => RideStatus::Requested,
            'origin_latitude' => 4.710989,
            'origin_longitude' => -74.072092,
        ]);
    }

    /**
     * Registra una conexión de broadcasting que, en vez de hablar con Reverb,
     * anota lo que se le pidió publicar (mismo patrón que
     * ShareRideLocationTest).
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

    private function uri(Ride $viaje): string
    {
        return "/api/v1/rides/{$viaje->id}/accept";
    }

    /**
     * El criterio de aceptación #3 dice "accepted o in_progress": ambos
     * estados tienen que rechazar la aceptación, no solo el primero.
     *
     * @return array<string, array{RideStatus}>
     */
    public static function estadosActivosDelConductor(): array
    {
        return [
            RideStatus::Accepted->value => [RideStatus::Accepted],
            RideStatus::InProgress->value => [RideStatus::InProgress],
        ];
    }
}
