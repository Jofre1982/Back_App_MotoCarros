<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RecordsBroadcasts;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/rides — ver openapi.yaml.
 *
 * Es el núcleo del producto: lo que fija esta suite es que el viaje nazca en
 * `requested` con su trayecto y su tarifa ya calculados, que sea del pasajero
 * que manda el token y no de quien venga en la entrada, y que un pasajero no
 * pueda tener dos viajes activos a la vez.
 */
class RequestRideTest extends TestCase
{
    use RecordsBroadcasts, RefreshDatabase;

    private const URI = '/api/v1/rides';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('maps.provider', 'google');
        Config::set('maps.google.key', 'test-key');

        Config::set('fares.currency', 'COP');
        Config::set('fares.base', 1500);
        Config::set('fares.per_kilometer', 800);
        Config::set('fares.per_minute', 100);
        Config::set('fares.per_waiting_minute', 60);
        Config::set('fares.minimum', 3000);
        Config::set('fares.rounding_step', 50);
    }

    public function test_crea_el_viaje_en_estado_requested_con_su_id_y_tarifa_estimada(): void
    {
        $this->fakeRuta(distanciaMetros: 7421, duracionSegundos: 842);
        $pasajero = User::factory()->create();

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $viaje = Ride::sole();

        $respuesta->assertExactJson([
            'data' => [
                'id' => $viaje->id,
                'status' => 'requested',
                'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
                'destination' => ['latitude' => 4.698, 'longitude' => -74.061],
                'distance_meters' => 7421,
                'duration_seconds' => 842,
                'currency' => 'COP',
                'estimated_fare' => 8850,
                // Todavía no lo aceptó nadie, y el campo viaja igual por el
                // mismo motivo que `started_at`: el schema `Ride` lo declara
                // obligatorio y nullable (historia #21).
                'driver' => null,
                'requested_at' => $viaje->created_at?->toIso8601String(),
                // El viaje nace sin empezar, pero el campo viaja igual: el
                // schema `Ride` lo declara obligatorio y nullable (historia
                // #19), así que el cliente lo encuentra siempre.
                'started_at' => null,
                // Mismo criterio para completar el viaje (historia #24): el
                // viaje recién nace, todavía muy lejos de completarse.
                'completed_at' => null,
                'final_fare' => null,
                // Ídem para el cobro (historia #25): no hay nada que cobrar
                // hasta que el viaje se completa.
                'payment' => null,
            ],
        ]);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'passenger_id' => $pasajero->id,
            'status' => RideStatus::Requested->value,
            'estimated_distance_meters' => 7421,
            'estimated_duration_seconds' => 842,
            'estimated_fare' => 8850,
            'currency' => 'COP',
        ]);
    }

    public function test_el_viaje_nace_sin_conductor_asignado(): void
    {
        // La asignación automática está fuera de alcance: son los conductores
        // quienes aceptan la solicitud disponible (historia #18).
        $this->fakeRuta();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated()
            ->assertJsonMissingPath('data.driver_id');

        $this->assertNull(Ride::sole()->driver_id);
    }

    public function test_el_viaje_queda_del_pasajero_del_token_y_no_de_otra_cuenta(): void
    {
        $this->fakeRuta();
        $otro = User::factory()->create();
        $propio = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($propio))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertDatabaseHas('rides', ['passenger_id' => $propio->id]);
        $this->assertDatabaseMissing('rides', ['passenger_id' => $otro->id]);
    }

    public function test_ignora_el_pasajero_el_estado_y_la_tarifa_que_vengan_en_la_entrada(): void
    {
        // Nada de lo que manda el cliente puede decidir de quién es el viaje,
        // en qué estado nace ni cuánto cuesta: el dueño sale del guard y los
        // números del proveedor de mapas más el motor de tarifa.
        $this->fakeRuta(distanciaMetros: 7421, duracionSegundos: 842);
        $victima = User::factory()->create();
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'passenger_id' => $victima->id,
                'status' => RideStatus::Completed->value,
                'estimated_fare' => 1,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('rides', [
            'passenger_id' => $pasajero->id,
            'status' => RideStatus::Requested->value,
            'estimated_fare' => 8850,
        ]);
        $this->assertDatabaseMissing('rides', ['passenger_id' => $victima->id]);
    }

    #[DataProvider('estadosActivos')]
    public function test_rechaza_un_segundo_viaje_cuando_ya_tiene_uno_activo(RideStatus $estado): void
    {
        $this->fakeRuta();
        $pasajero = User::factory()->create();
        Ride::factory()->for($pasajero, 'passenger')->create(['status' => $estado]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');

        $this->assertDatabaseCount('rides', 1);
    }

    /**
     * @return array<string, array{RideStatus}>
     */
    public static function estadosActivos(): array
    {
        return array_reduce(
            RideStatus::active(),
            static fn (array $casos, RideStatus $estado): array => [...$casos, $estado->value => [$estado]],
            [],
        );
    }

    public function test_no_consulta_al_proveedor_de_mapas_si_el_pasajero_ya_tiene_un_viaje_activo(): void
    {
        // Cada consulta al proveedor se paga: el rechazo tiene que resolverse
        // antes de gastarla, igual que el 403 del conductor.
        $this->fakeRuta();
        $pasajero = User::factory()->create();
        Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_permite_solicitar_otro_viaje_cuando_el_anterior_ya_termino(): void
    {
        $this->fakeRuta();
        $pasajero = User::factory()->create();
        Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Completed]);
        Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Cancelled]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertDatabaseCount('rides', 3);
    }

    public function test_el_viaje_activo_de_otro_pasajero_no_bloquea_la_solicitud(): void
    {
        $this->fakeRuta();
        Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertDatabaseCount('rides', 2);
    }

    public function test_rechaza_un_destino_igual_al_origen(): void
    {
        $this->fakeRuta();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
                'destination' => ['latitude' => 4.710989, 'longitude' => -74.072092],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination');

        $this->assertDatabaseCount('rides', 0);
        Http::assertNothingSent();
    }

    #[DataProvider('coordenadasInvalidas')]
    public function test_rechaza_coordenadas_invalidas(array $entrada, array $camposConError): void
    {
        $this->fakeRuta();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $entrada)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($camposConError);

        $this->assertDatabaseCount('rides', 0);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{array<string, mixed>, array<int, string>}>
     */
    public static function coordenadasInvalidas(): array
    {
        $validas = [
            'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
            'destination' => ['latitude' => 4.698, 'longitude' => -74.061],
        ];

        return [
            'cuerpo vacío' => [[], [
                'origin.latitude', 'origin.longitude',
                'destination.latitude', 'destination.longitude',
            ]],
            'sin destino' => [['origin' => $validas['origin']], [
                'destination.latitude', 'destination.longitude',
            ]],
            'latitud de origen fuera de rango' => [
                [...$validas, 'origin' => ['latitude' => 95.0, 'longitude' => -74.0]],
                ['origin.latitude'],
            ],
            'longitud de destino fuera de rango' => [
                [...$validas, 'destination' => ['latitude' => 4.7, 'longitude' => 200.0]],
                ['destination.longitude'],
            ],
            'coordenada no numérica' => [
                [...$validas, 'origin' => ['latitude' => 'norte', 'longitude' => -74.0]],
                ['origin.latitude'],
            ],
        ];
    }

    public function test_responde_422_cuando_no_hay_ruta_entre_las_coordenadas(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response(['routes' => []]),
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);

        // Sin trayecto no hay tarifa, y sin tarifa no hay viaje que guardar:
        // un viaje a medias dejaría al pasajero con un activo que no pidió.
        $this->assertDatabaseCount('rides', 0);
    }

    public function test_la_cuenta_de_conductor_no_puede_solicitar_un_viaje(): void
    {
        // 403 y no 422: no es una entrada que se pueda corregir mandando otros
        // datos, es una operación que el rol no tiene. Un conductor consigue
        // viajes aceptando los que ya existen (historia #18).
        $this->fakeRuta();

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('rides', 0);
        Http::assertNothingSent();
    }

    public function test_al_conductor_le_responde_403_aunque_los_datos_sean_invalidos(): void
    {
        // La autorización corre antes que la validación: si no, el 422 le
        // diría a una cuenta sin permiso qué forma tiene que tener la entrada.
        $this->fakeRuta();

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson(self::URI, [])
            ->assertForbidden();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $this->fakeRuta();

        $this->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('rides', 0);
        Http::assertNothingSent();
    }

    public function test_rechaza_la_solicitud_con_un_token_ilegible(): void
    {
        $this->fakeRuta();

        $this->withToken('no-es-un-jwt')
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_rechaza_la_solicitud_con_un_token_expirado(): void
    {
        $this->fakeRuta();
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->travel(30)->minutes();

        $this->withToken($token)
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('rides', 0);
    }

    /**
     * Historia #17: el viaje nuevo avisa a los conductores disponibles
     * dentro del radio configurado por el canal `driver.{id}`.
     */
    public function test_avisa_por_el_canal_del_conductor_disponible_dentro_del_radio(): void
    {
        $grabador = $this->grabarBroadcasts();
        $this->fakeRuta(distanciaMetros: 7421, duracionSegundos: 842);
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $viaje = Ride::sole();

        $this->assertCount(1, $grabador->emitidos);
        [$canales, $evento, $payload] = $grabador->emitidos[0];

        $this->assertSame(["private-driver.{$conductor->id}"], $canales);
        $this->assertSame('ride.requested', $evento);
        $this->assertSame($viaje->id, $payload['ride_id']);
        $this->assertSame(4.710989, $payload['origin']['latitude']);
        $this->assertSame(-74.061, $payload['destination']['longitude']);
        $this->assertSame('COP', $payload['currency']);
        $this->assertSame(8850, $payload['estimated_fare']);
    }

    public function test_avisa_a_varios_conductores_disponibles_cercanos_a_la_vez(): void
    {
        $grabador = $this->grabarBroadcasts();
        $this->fakeRuta();
        $primero = User::factory()->driver()->create();
        $segundo = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $primero->id]);
        DriverProfile::factory()->available(latitude: 4.711, longitude: -74.0715)->create(['user_id' => $segundo->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertCount(1, $grabador->emitidos);
        [$canales] = $grabador->emitidos[0];
        $this->assertEqualsCanonicalizing(
            ["private-driver.{$primero->id}", "private-driver.{$segundo->id}"],
            $canales,
        );
    }

    public function test_no_avisa_a_un_conductor_marcado_no_disponible(): void
    {
        $grabador = $this->grabarBroadcasts();
        $this->fakeRuta();
        $conductor = User::factory()->driver()->create();
        // `is_available` en falso por defecto (ver la migración).
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertSame([], $grabador->emitidos);
    }

    public function test_no_avisa_a_un_conductor_disponible_fuera_del_radio_configurado(): void
    {
        $grabador = $this->grabarBroadcasts();
        $this->fakeRuta();
        $conductor = User::factory()->driver()->create();
        // A varios kilómetros del origen de `datosValidos()` (4.710989,
        // -74.072092): muy lejos del radio por defecto (3000 m).
        DriverProfile::factory()->available(latitude: 4.9, longitude: -74.3)->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertSame([], $grabador->emitidos);
    }

    public function test_no_dispara_ningun_evento_si_no_hay_conductores_disponibles_cerca(): void
    {
        $grabador = $this->grabarBroadcasts();
        $this->fakeRuta();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertSame([], $grabador->emitidos);
    }

    private function fakeRuta(int $distanciaMetros = 7421, int $duracionSegundos = 842): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'distanceMeters' => $distanciaMetros,
                    'duration' => "{$duracionSegundos}s",
                ]],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(): array
    {
        return [
            'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
            'destination' => ['latitude' => 4.698, 'longitude' => -74.061],
        ];
    }
}
