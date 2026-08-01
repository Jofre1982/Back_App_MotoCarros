<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/rides/{id}/receipt — ver openapi.yaml.
 *
 * Historia #26: el pasajero dueño de un viaje completado consulta el
 * desglose de lo que se le cobró.
 */
class ShowRideReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pasajero_dueno_ve_el_recibo_de_su_viaje_completado(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'currency' => 'COP',
            'final_fare' => 8450,
            'completed_at' => now(),
        ]);
        Payment::factory()->for($viaje)->create([
            'amount' => 8450,
            'currency' => 'COP',
            'base_fare' => 1500,
            'distance_fare' => 5937,
            'time_fare' => 1000,
            'waiting_fee' => 0,
            'subtotal' => 8437,
            'minimum_applied' => false,
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.ride_id', $viaje->id);
        $respuesta->assertJsonPath('data.currency', 'COP');
        $respuesta->assertJsonPath('data.base_fare', 1500);
        $respuesta->assertJsonPath('data.distance_fare', 5937);
        $respuesta->assertJsonPath('data.time_fare', 1000);
        $respuesta->assertJsonPath('data.waiting_fee', 0);
        $respuesta->assertJsonPath('data.subtotal', 8437);
        $respuesta->assertJsonPath('data.minimum_applied', false);
        $respuesta->assertJsonPath('data.total', 8450);
        $respuesta->assertJsonPath('data.payment_status', 'paid');
        $this->assertNotNull($respuesta->json('data.completed_at'));
    }

    public function test_rechaza_un_viaje_que_no_pertenece_al_pasajero_autenticado(): void
    {
        $dueno = User::factory()->create();
        $otroPasajero = User::factory()->create();
        $viaje = Ride::factory()->for($dueno, 'passenger')->create([
            'status' => RideStatus::Completed,
            'final_fare' => 8450,
            'completed_at' => now(),
        ]);
        Payment::factory()->for($viaje)->create();

        $this->withToken(JWTAuth::fromUser($otroPasajero))
            ->getJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_al_conductor_asignado(): void
    {
        // El recibo es del pasajero que pagó, no del conductor que llevó el
        // viaje (historia #26): a diferencia de `ShowRideController`, acá el
        // conductor asignado tampoco puede verlo.
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'driver_id' => $conductor->id,
            'final_fare' => 8450,
            'completed_at' => now(),
        ]);
        Payment::factory()->for($viaje)->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson($this->uri($viaje))
            ->assertForbidden();
    }

    #[DataProvider('estadosSinRecibo')]
    public function test_rechaza_un_viaje_que_no_esta_completado(RideStatus $estado): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => $estado,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson($this->uri($viaje))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');
    }

    public function test_rechaza_un_viaje_completado_sin_pago_registrado(): void
    {
        // No debería ocurrir en producción —`CompleteRideAction` siempre
        // dispara el cobro—, pero el Form Request no asume que `payment`
        // exista solo porque el estado es `completed`.
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'final_fare' => 8450,
            'completed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson($this->uri($viaje))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');
    }

    public function test_responde_404_cuando_el_viaje_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson('/api/v1/rides/999999/receipt')
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'final_fare' => 8450,
            'completed_at' => now(),
        ]);
        Payment::factory()->for($viaje)->create();

        $this->getJson($this->uri($viaje))
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    private function uri(Ride $viaje): string
    {
        return "/api/v1/rides/{$viaje->id}/receipt";
    }

    /**
     * @return array<string, array{RideStatus}>
     */
    public static function estadosSinRecibo(): array
    {
        return [
            RideStatus::Requested->value => [RideStatus::Requested],
            RideStatus::Accepted->value => [RideStatus::Accepted],
            RideStatus::InProgress->value => [RideStatus::InProgress],
            RideStatus::Cancelled->value => [RideStatus::Cancelled],
        ];
    }
}
