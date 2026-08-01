<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/rides/{id} — ver openapi.yaml.
 *
 * Historia #21: el pasajero sigue su viaje en vivo por el canal `ride.{id}`,
 * y este endpoint es la consulta puntual con la que arranca o se recupera si
 * perdió el canal. Lo consultan los mismos dos que entran al canal: el
 * pasajero dueño y el conductor asignado.
 */
class ShowRideTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pasajero_consulta_el_estado_de_su_viaje(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create();

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.id', $viaje->id);
        $respuesta->assertJsonPath('data.status', RideStatus::Requested->value);
        $respuesta->assertJsonPath('data.estimated_fare', $viaje->estimated_fare);
    }

    /**
     * El criterio de aceptación de #21 sobre un viaje todavía sin aceptar:
     * la respuesta tiene que decir que no hay conductor. Y no hay ubicación
     * que seguir justamente porque no hay conductor — la posición no viaja
     * nunca por esta respuesta, solo por el evento `location.updated` del
     * canal, que publica el conductor asignado (#20).
     */
    public function test_un_viaje_sin_aceptar_publica_el_conductor_en_nulo(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Requested,
            'driver_id' => null,
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.status', RideStatus::Requested->value);
        $respuesta->assertJsonPath('data.driver', null);

        // Presente y en `null`, no ausente: el contrato lo declara obligatorio
        // para que el cliente no distinga "todavía no hay conductor" de "esta
        // respuesta no lo trae".
        $this->assertArrayHasKey('driver', $respuesta->json('data'));
    }

    #[DataProvider('estadosConConductorAsignado')]
    public function test_un_viaje_con_conductor_publica_quien_es(RideStatus $estado): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create(['name' => 'Carlos Pérez']);
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => $estado,
            'driver_id' => $conductor->id,
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.status', $estado->value);
        $respuesta->assertJsonPath('data.driver.id', $conductor->id);
        $respuesta->assertJsonPath('data.driver.name', 'Carlos Pérez');
    }

    /**
     * @return array<string, array{RideStatus}>
     */
    public static function estadosConConductorAsignado(): array
    {
        return [
            RideStatus::Accepted->value => [RideStatus::Accepted],
            RideStatus::InProgress->value => [RideStatus::InProgress],
        ];
    }

    /**
     * El conductor asignado ve el viaje por el mismo endpoint: es la otra
     * mitad del canal `ride.{id}`, no un tercero.
     */
    public function test_el_conductor_asignado_tambien_consulta_el_viaje(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson($this->uri($viaje))
            ->assertOk()
            ->assertJsonPath('data.id', $viaje->id);
    }

    /**
     * El conductor asignado no lleva datos de contacto ni de la moto: ninguna
     * historia los pidió todavía y publicar el teléfono de una persona a la
     * otra es una decisión de producto, no de serialización.
     */
    public function test_el_conductor_publicado_no_lleva_datos_de_contacto(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        $conductorPublicado = $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson($this->uri($viaje))
            ->assertOk()
            ->json('data.driver');

        $this->assertSame(['id', 'name'], array_keys($conductorPublicado));
    }

    public function test_rechaza_a_quien_no_participa_del_viaje(): void
    {
        $viaje = Ride::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    /**
     * Un conductor cualquiera tampoco: aceptar un viaje disponible es otra
     * operación (#18) y no pasa por acá.
     */
    public function test_rechaza_a_un_conductor_que_no_es_el_asignado(): void
    {
        $asignado = User::factory()->driver()->create();
        $otroConductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $asignado->id,
        ]);

        $this->withToken(JWTAuth::fromUser($otroConductor))
            ->getJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_responde_404_cuando_el_viaje_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson('/api/v1/rides/999999')
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $viaje = Ride::factory()->create();

        $this->getJson($this->uri($viaje))
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    private function uri(Ride $viaje): string
    {
        return "/api/v1/rides/{$viaje->id}";
    }
}
