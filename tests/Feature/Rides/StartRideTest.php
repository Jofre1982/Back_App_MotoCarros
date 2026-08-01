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
 * Contrato de POST /api/v1/rides/{id}/start — ver openapi.yaml.
 *
 * Historia #19: el conductor que ya aceptó el viaje marca que recogió al
 * pasajero. Lo que distingue esta operación de aceptar es de quién es el
 * permiso: acá no vale cualquier conductor, solo el asignado a ese viaje.
 */
class StartRideTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_conductor_asignado_inicia_su_viaje_aceptado(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = $this->viajeAceptadoPor($conductor);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.id', $viaje->id);
        $respuesta->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::InProgress->value,
            'driver_id' => $conductor->id,
        ]);
    }

    public function test_registra_la_hora_de_inicio(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = $this->viajeAceptadoPor($conductor);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertOk();

        // La hora de inicio es un criterio de aceptación por sí misma, y el
        // contrato la publica: sin ella el pasajero no puede saber desde
        // cuándo está en curso el viaje que está viendo.
        $this->assertNotNull($respuesta->json('data.started_at'));
        $this->assertNotNull($viaje->refresh()->started_at);
    }

    public function test_el_viaje_no_iniciado_publica_started_at_nulo(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/accept")
            ->assertOk();

        // El campo está siempre presente, no solo cuando el viaje ya empezó:
        // así el cliente no tiene que distinguir "todavía no empezó" de "esta
        // respuesta no lo trae" (ver el schema `Ride` en openapi.yaml). La
        // estructura se afirma aparte del valor a propósito: `assertJsonPath`
        // contra `null` también pasa si la clave no viene, que es justo el
        // caso que este test existe para descartar.
        $respuesta->assertJsonStructure(['data' => ['started_at']]);
        $respuesta->assertJsonPath('data.started_at', null);
    }

    public function test_rechaza_a_un_conductor_que_no_es_el_asignado(): void
    {
        $asignado = User::factory()->driver()->create();
        $otroConductor = User::factory()->driver()->create();
        $viaje = $this->viajeAceptadoPor($asignado);

        $this->withToken(JWTAuth::fromUser($otroConductor))
            ->postJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Accepted->value,
            'started_at' => null,
        ]);
    }

    public function test_rechaza_al_pasajero_del_viaje(): void
    {
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Accepted->value,
        ]);
    }

    #[DataProvider('estadosQueNoSePuedenIniciar')]
    public function test_rechaza_un_viaje_que_no_esta_aceptado(RideStatus $estado): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => $estado,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => $estado->value,
        ]);
    }

    public function test_rechaza_un_viaje_requested_sin_conductor_asignado(): void
    {
        // Sin conductor asignado no hay a quién dejarlo iniciar, así que la
        // Policy corta antes que la validación de estado: es 403 y no 422.
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertForbidden();

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Requested->value,
        ]);
    }

    public function test_responde_404_cuando_el_viaje_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson('/api/v1/rides/999999/start')
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $viaje = $this->viajeAceptadoPor(User::factory()->driver()->create());

        $this->postJson($this->uri($viaje))
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Accepted->value,
            'started_at' => null,
        ]);
    }

    private function viajeAceptadoPor(User $conductor): Ride
    {
        return Ride::factory()->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);
    }

    private function uri(Ride $viaje): string
    {
        return "/api/v1/rides/{$viaje->id}/start";
    }

    /**
     * El criterio de aceptación #3 nombra `in_progress` y el viaje cancelado;
     * `completed` va por el mismo camino y se cubre por completitud del ciclo
     * de vida. `requested` queda fuera de este conjunto a propósito: ahí no
     * hay conductor asignado y la respuesta correcta es 403, con su propio
     * test.
     *
     * @return array<string, array{RideStatus}>
     */
    public static function estadosQueNoSePuedenIniciar(): array
    {
        return [
            RideStatus::InProgress->value => [RideStatus::InProgress],
            RideStatus::Completed->value => [RideStatus::Completed],
            RideStatus::Cancelled->value => [RideStatus::Cancelled],
        ];
    }
}
