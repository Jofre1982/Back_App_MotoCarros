<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideMode;
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
 * Viaje "solo recogida" (historia #95): el pasajero marca dónde lo recogen y
 * escribe a dónde va; el precio lo acuerdan y el conductor lo registra al
 * aceptar. Convive con el viaje de catálogo, que no cambia (ver
 * `RequestRideTest`, `AcceptRideTest`, `CompleteRideTest`).
 */
class PickupOnlyRideTest extends TestCase
{
    use RecordsBroadcasts, RefreshDatabase;

    public function test_crea_el_viaje_solo_recogida_sin_sitio_ni_tarifa(): void
    {
        $pasajero = User::factory()->create();

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson('/api/v1/rides', $this->datosValidos())
            ->assertCreated();

        $viaje = Ride::sole();

        $respuesta
            ->assertJsonPath('data.id', $viaje->id)
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.mode', 'pickup_only')
            ->assertJsonPath('data.destination', null)
            ->assertJsonPath('data.destination_text', 'Casa de mi mamá, barrio La Esperanza')
            ->assertJsonPath('data.estimated_fare', null)
            ->assertJsonPath('data.agreed_price', null)
            ->assertJsonPath('data.currency', 'COP')
            ->assertJsonPath('data.passenger_count', 2);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'passenger_id' => $pasajero->id,
            'status' => RideStatus::Requested->value,
            'mode' => RideMode::PickupOnly->value,
            'destination_site_id' => null,
            'destination_text' => 'Casa de mi mamá, barrio La Esperanza',
            'estimated_fare' => null,
            'agreed_price' => null,
        ]);
    }

    public function test_guarda_el_destino_sin_espacios_sobrantes(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides', [...$this->datosValidos(), 'destination_text' => '   al hospital  '])
            ->assertCreated()
            ->assertJsonPath('data.destination_text', 'al hospital');
    }

    public function test_ignora_un_sitio_mandado_junto_con_un_viaje_solo_recogida(): void
    {
        $sitio = $this->sitioConTarifa();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides', [...$this->datosValidos(), 'destination_site_id' => $sitio->id])
            ->assertCreated()
            ->assertJsonPath('data.destination', null)
            ->assertJsonPath('data.estimated_fare', null);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    #[DataProvider('destinosInvalidos')]
    public function test_rechaza_un_destino_escrito_invalido(array $cambios): void
    {
        $datos = array_merge($this->datosValidos(), $cambios);
        $datos = array_filter($datos, static fn ($valor) => $valor !== '__omitir__');

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['destination_text']);

        $this->assertDatabaseCount('rides', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function destinosInvalidos(): array
    {
        return [
            'demasiado largo' => [['destination_text' => str_repeat('a', 256)]],
            'no es texto' => [['destination_text' => ['hospital']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    #[DataProvider('sinDestinoEscrito')]
    public function test_el_destino_escrito_es_opcional(array $cambios): void
    {
        $datos = array_merge($this->datosValidos(), $cambios);
        $datos = array_filter($datos, static fn ($valor) => $valor !== '__omitir__');

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides', $datos)
            ->assertCreated()
            ->assertJsonPath('data.destination_text', null);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function sinDestinoEscrito(): array
    {
        return [
            'sin el campo' => [['destination_text' => '__omitir__']],
            'vacío' => [['destination_text' => '']],
            'solo espacios' => [['destination_text' => '    ']],
        ];
    }

    public function test_rechaza_una_modalidad_desconocida(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides', [...$this->datosValidos(), 'mode' => 'teletransporte'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mode']);
    }

    public function test_avisa_a_los_conductores_cercanos_con_el_destino_escrito_y_precio_a_acordar(): void
    {
        $grabador = $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides', $this->datosValidos())
            ->assertCreated();

        $this->assertCount(1, $grabador->emitidos);
        [$canales, $evento, $payload] = $grabador->emitidos[0];

        $this->assertSame(["private-driver.{$conductor->id}"], $canales);
        $this->assertSame('ride.requested', $evento);
        $this->assertSame(Ride::sole()->id, $payload['ride_id']);
        $this->assertSame('pickup_only', $payload['mode']);
        $this->assertNull($payload['destination']);
        $this->assertSame('Casa de mi mamá, barrio La Esperanza', $payload['destination_text']);
        $this->assertNull($payload['estimated_fare']);
        $this->assertSame('COP', $payload['currency']);
    }

    public function test_el_viaje_de_catalogo_avisa_con_su_modalidad_y_sin_texto(): void
    {
        $grabador = $this->grabarBroadcasts();
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $conductor->id]);
        $sitio = $this->sitioConTarifa();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides', [
                'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
                'destination_site_id' => $sitio->id,
                'passenger_count' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'catalog')
            ->assertJsonPath('data.destination_text', null)
            ->assertJsonPath('data.agreed_price', null);

        $payload = $grabador->emitidos[0][2];
        $this->assertSame('catalog', $payload['mode']);
        $this->assertSame($sitio->id, $payload['destination']['site_id']);
        $this->assertNull($payload['destination_text']);
        $this->assertSame(5000, $payload['estimated_fare']);
    }

    public function test_el_conductor_acepta_registrando_el_precio_acordado(): void
    {
        $viaje = Ride::factory()->pickupOnly()->create();
        $conductor = $this->conductor();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/accept", ['agreed_price' => 7000])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.agreed_price', 7000)
            ->assertJsonPath('data.driver.id', $conductor->id);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Accepted->value,
            'driver_id' => $conductor->id,
            'agreed_price' => 7000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entrada
     */
    #[DataProvider('preciosInvalidos')]
    public function test_rechaza_aceptar_un_viaje_solo_recogida_sin_precio_valido(array $entrada): void
    {
        $viaje = Ride::factory()->pickupOnly()->create();

        $this->withToken(JWTAuth::fromUser($this->conductor()))
            ->postJson("/api/v1/rides/{$viaje->id}/accept", $entrada)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agreed_price']);

        $this->assertSame(RideStatus::Requested, $viaje->refresh()->status);
        $this->assertNull($viaje->driver_id);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function preciosInvalidos(): array
    {
        return [
            'sin precio' => [[]],
            'cero' => [['agreed_price' => 0]],
            'negativo' => [['agreed_price' => -500]],
            'decimal' => [['agreed_price' => 4500.5]],
            'texto' => [['agreed_price' => 'cinco mil']],
        ];
    }

    public function test_el_viaje_de_catalogo_se_acepta_sin_precio_e_ignora_uno_mandado(): void
    {
        $viaje = Ride::factory()->create(['estimated_fare' => 4000]);

        $this->withToken(JWTAuth::fromUser($this->conductor()))
            ->postJson("/api/v1/rides/{$viaje->id}/accept", ['agreed_price' => 99999])
            ->assertOk()
            ->assertJsonPath('data.agreed_price', null)
            ->assertJsonPath('data.estimated_fare', 4000);

        $this->assertNull($viaje->refresh()->agreed_price);
    }

    public function test_el_pasajero_ve_el_precio_acordado_al_consultar_el_viaje(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->pickupOnly()->create(['passenger_id' => $pasajero->id]);

        $this->withToken(JWTAuth::fromUser($this->conductor()))
            ->postJson("/api/v1/rides/{$viaje->id}/accept", ['agreed_price' => 6500])
            ->assertOk();

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertOk()
            ->assertJsonPath('data.mode', 'pickup_only')
            ->assertJsonPath('data.agreed_price', 6500)
            ->assertJsonPath('data.destination_text', $viaje->destination_text);
    }

    public function test_al_completarse_cobra_el_precio_acordado(): void
    {
        $conductor = $this->conductor();
        $viaje = Ride::factory()->pickupOnly()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
            'agreed_price' => 8000,
            'started_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.final_fare', 8000);

        $this->assertDatabaseHas('payments', [
            'ride_id' => $viaje->id,
            'amount' => 8000,
        ]);

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($viaje->passenger))
            ->getJson("/api/v1/rides/{$viaje->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.total', 8000);
    }

    public function test_aparece_en_el_historial_y_en_las_ganancias_con_su_precio_acordado(): void
    {
        $conductor = $this->conductor();
        $viaje = Ride::factory()->pickupOnly()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
            'agreed_price' => 9000,
            'started_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/complete")
            ->assertOk();

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($viaje->passenger))
            ->getJson('/api/v1/me/rides')
            ->assertOk()
            ->assertJsonPath('data.0.id', $viaje->id)
            ->assertJsonPath('data.0.final_fare', 9000)
            ->assertJsonPath('data.0.destination_text', $viaje->destination_text);

        $this->cambiarDeCuenta();
        $hoy = now()->toDateString();
        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson("/api/v1/me/earnings?from={$hoy}&to={$hoy}")
            ->assertOk()
            ->assertJsonPath('data.total_earned', 9000)
            ->assertJsonPath('data.completed_rides', 1);
    }

    public function test_la_estimacion_rechaza_un_viaje_solo_recogida(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides/estimate', $this->datosValidos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mode']);
    }

    /**
     * El guard y el singleton de JWT recuerdan el token de la request
     * anterior dentro del mismo test; sin esto la segunda cuenta se
     * autenticaría como la primera.
     */
    private function cambiarDeCuenta(): void
    {
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();
    }

    private function conductor(): User
    {
        return User::factory()->driver()->create();
    }

    private function sitioConTarifa(): Site
    {
        $sitio = Site::factory()->create();
        SiteFare::factory()->create([
            'site_id' => $sitio->id,
            'vehicle_type' => VehicleType::Motocarro,
            'day_price' => 5000,
        ]);

        return $sitio;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(): array
    {
        return [
            'mode' => 'pickup_only',
            'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
            'destination_text' => 'Casa de mi mamá, barrio La Esperanza',
            'passenger_count' => 2,
        ];
    }
}
