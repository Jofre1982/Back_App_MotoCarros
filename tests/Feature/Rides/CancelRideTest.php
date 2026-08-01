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
 * Contrato de POST /api/v1/rides/{id}/cancel — ver openapi.yaml.
 *
 * Cubre la ventana entre pedir el viaje y que algún conductor lo acepte
 * (historia #16): no genera cargo porque hoy no existe nada que cobrar en
 * `requested` (el motor de tarifa solo entra al completar el viaje, historia
 * #24), así que basta con que el viaje quede `cancelled` sin dejar rastro
 * adicional.
 */
class CancelRideTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pasajero_cancela_su_viaje_todavia_no_aceptado(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Requested]);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.id', $viaje->id);
        $respuesta->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Cancelled->value,
        ]);
    }

    public function test_cancelar_no_borra_el_viaje_ni_crea_otro(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $this->assertDatabaseCount('rides', 1);
    }

    #[DataProvider('estadosYaNoRequested')]
    public function test_rechaza_cancelar_un_viaje_que_ya_no_esta_requested(RideStatus $estado): void
    {
        // Es 422 y no 403: el pasajero sigue siendo dueño del viaje, lo que
        // cambia es el flujo a seguir (historia #22/#23 para viajes
        // aceptados en adelante).
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create(['status' => $estado]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');

        $this->assertDatabaseHas('rides', ['id' => $viaje->id, 'status' => $estado->value]);
    }

    /**
     * @return array<string, array{RideStatus}>
     */
    public static function estadosYaNoRequested(): array
    {
        return array_reduce(
            array_filter(RideStatus::cases(), static fn (RideStatus $estado): bool => $estado !== RideStatus::Requested),
            static fn (array $casos, RideStatus $estado): array => [...$casos, $estado->value => [$estado]],
            [],
        );
    }

    public function test_rechaza_cancelar_el_viaje_de_otro_pasajero(): void
    {
        $dueno = User::factory()->create();
        $otro = User::factory()->create();
        $viaje = Ride::factory()->for($dueno, 'passenger')->create(['status' => RideStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($otro))
            ->postJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', ['id' => $viaje->id, 'status' => RideStatus::Requested->value]);
    }

    public function test_responde_404_cuando_el_viaje_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/rides/999999/cancel')
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
        return "/api/v1/rides/{$viaje->id}/cancel";
    }
}
