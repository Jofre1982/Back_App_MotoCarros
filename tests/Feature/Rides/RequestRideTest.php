<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\PricingUnit;
use App\Enums\RideStatus;
use App\Enums\VehicleType;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\Site;
use App\Models\SiteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RecordsBroadcasts;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/rides — ver openapi.yaml.
 *
 * Es el núcleo del producto: lo que fija esta suite es que el viaje nazca en
 * `requested` con su destino y su tarifa ya fijados (historia #87: el
 * destino es un sitio con precio fijo, no coordenadas libres), que sea del
 * pasajero que manda el token y no de quien venga en la entrada, y que un
 * pasajero no pueda tener dos viajes activos a la vez.
 */
class RequestRideTest extends TestCase
{
    use RecordsBroadcasts, RefreshDatabase;

    private const URI = '/api/v1/rides';

    private Site $sitio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sitio = $this->siteConTarifa(8850);
    }

    public function test_crea_el_viaje_en_estado_requested_con_su_id_y_tarifa_estimada(): void
    {
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
                'destination' => ['site_id' => $this->sitio->id, 'name' => $this->sitio->name],
                'passenger_count' => 1,
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
            'destination_site_id' => $this->sitio->id,
            'passenger_count' => 1,
            'estimated_fare' => 8850,
            'currency' => 'COP',
        ]);
    }

    public function test_multiplica_el_precio_por_pasajero_cuando_el_sitio_cobra_por_persona(): void
    {
        $sitio = $this->siteConTarifa(4000, PricingUnit::PerPerson);

        $respuesta = $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'destination_site_id' => $sitio->id,
                'passenger_count' => 3,
            ])
            ->assertCreated();

        $respuesta->assertJsonPath('data.estimated_fare', 12000);
    }

    public function test_el_viaje_nace_sin_conductor_asignado(): void
    {
        // La asignación automática está fuera de alcance: son los conductores
        // quienes aceptan la solicitud disponible (historia #18).
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated()
            ->assertJsonMissingPath('data.driver_id');

        $this->assertNull(Ride::sole()->driver_id);
    }

    public function test_el_viaje_queda_del_pasajero_del_token_y_no_de_otra_cuenta(): void
    {
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
        // en qué estado nace ni cuánto cuesta: el dueño sale del guard y el
        // monto del precio fijo del sitio.
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

    public function test_permite_solicitar_otro_viaje_cuando_el_anterior_ya_termino(): void
    {
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
        Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertDatabaseCount('rides', 2);
    }

    public function test_rechaza_un_sitio_sin_precio_de_pasajero(): void
    {
        $sitioSinPrecio = Site::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'destination_site_id' => $sitioSinPrecio->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination_site_id');

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_rechaza_un_sitio_que_solo_tiene_precio_de_motocarga(): void
    {
        $sitio = Site::factory()->create();
        SiteFare::factory()->create([
            'site_id' => $sitio->id,
            'vehicle_type' => VehicleType::Motocarga,
            'pricing_unit' => PricingUnit::PerTrip,
            'day_price' => 20000,
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                ...$this->datosValidos(),
                'destination_site_id' => $sitio->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destination_site_id');
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_entradas_invalidas(array $entrada, array $camposConError): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $entrada)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($camposConError);

        $this->assertDatabaseCount('rides', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<int, string>}>
     */
    public static function entradasInvalidas(): array
    {
        $origenValido = ['latitude' => 4.710989, 'longitude' => -74.072092];

        return [
            'cuerpo vacío' => [[], [
                'origin.latitude', 'origin.longitude', 'destination_site_id', 'passenger_count',
            ]],
            'sin destino ni pasajeros' => [['origin' => $origenValido], [
                'destination_site_id', 'passenger_count',
            ]],
            'latitud de origen fuera de rango' => [
                ['origin' => ['latitude' => 95.0, 'longitude' => -74.0], 'destination_site_id' => 1, 'passenger_count' => 1],
                ['origin.latitude'],
            ],
            'coordenada de origen no numérica' => [
                ['origin' => ['latitude' => 'norte', 'longitude' => -74.0], 'destination_site_id' => 1, 'passenger_count' => 1],
                ['origin.latitude'],
            ],
            'sitio inexistente' => [
                ['origin' => $origenValido, 'destination_site_id' => 999999, 'passenger_count' => 1],
                ['destination_site_id'],
            ],
            'cero pasajeros' => [
                ['origin' => $origenValido, 'destination_site_id' => 1, 'passenger_count' => 0],
                ['passenger_count'],
            ],
            'mas de tres pasajeros (capacidad del motocarro)' => [
                ['origin' => $origenValido, 'destination_site_id' => 1, 'passenger_count' => 4],
                ['passenger_count'],
            ],
        ];
    }

    public function test_la_cuenta_de_conductor_no_puede_solicitar_un_viaje(): void
    {
        // 403 y no 422: no es una entrada que se pueda corregir mandando otros
        // datos, es una operación que el rol no tiene. Un conductor consigue
        // viajes aceptando los que ya existen (historia #18).
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_al_conductor_le_responde_403_aunque_los_datos_sean_invalidos(): void
    {
        // La autorización corre antes que la validación: si no, el 422 le
        // diría a una cuenta sin permiso qué forma tiene que tener la entrada.
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson(self::URI, [])
            ->assertForbidden();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $this->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_rechaza_la_solicitud_con_un_token_ilegible(): void
    {
        $this->withToken('no-es-un-jwt')
            ->postJson(self::URI, $this->datosValidos())
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_rechaza_la_solicitud_con_un_token_expirado(): void
    {
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
        $this->assertSame($this->sitio->id, $payload['destination']['site_id']);
        $this->assertSame($this->sitio->name, $payload['destination']['name']);
        $this->assertSame(1, $payload['passenger_count']);
        $this->assertSame('COP', $payload['currency']);
        $this->assertSame(8850, $payload['estimated_fare']);
    }

    public function test_avisa_a_varios_conductores_disponibles_cercanos_a_la_vez(): void
    {
        $grabador = $this->grabarBroadcasts();
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

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, $this->datosValidos())
            ->assertCreated();

        $this->assertSame([], $grabador->emitidos);
    }

    private function siteConTarifa(
        int $dayPrice,
        PricingUnit $pricingUnit = PricingUnit::PerTrip,
    ): Site {
        $site = Site::factory()->create();
        SiteFare::factory()->create([
            'site_id' => $site->id,
            'vehicle_type' => VehicleType::Motocarro,
            'pricing_unit' => $pricingUnit,
            'day_price' => $dayPrice,
        ]);

        return $site;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(): array
    {
        return [
            'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
            'destination_site_id' => $this->sitio->id,
            'passenger_count' => 1,
        ];
    }
}
