<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_rechaza_al_conductor_que_ya_tiene_un_viaje_propio_activo(): void
    {
        $conductor = User::factory()->driver()->create();
        Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);
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

    private function uri(Ride $viaje): string
    {
        return "/api/v1/rides/{$viaje->id}/accept";
    }
}
