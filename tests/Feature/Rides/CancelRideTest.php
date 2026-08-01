<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/rides/{id}/cancel — ver openapi.yaml.
 *
 * Cubre dos ventanas del ciclo de vida del viaje del lado del pasajero: antes
 * de que un conductor lo acepte (`requested`, historia #16), donde no genera
 * cargo porque hoy no existe nada que cobrar en ese estado (el motor de
 * tarifa solo entra al completar el viaje, historia #24); y después de que un
 * conductor ya lo aceptó (`accepted`, historia #22), donde sí indica que
 * aplica una penalización por cancelación tardía, aunque el cobro efectivo
 * quede fuera de esta historia. Y del lado del conductor asignado, que
 * devuelve el viaje al pool en vez de cancelarlo (`accepted`, historia #23).
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
        $respuesta->assertJsonPath('data.cancellation_fee_applies', false);

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

    public function test_el_pasajero_cancela_su_viaje_ya_aceptado(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.id', $viaje->id);
        $respuesta->assertJsonPath('data.status', 'cancelled');
        $respuesta->assertJsonPath('data.cancellation_fee_applies', true);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Cancelled->value,
        ]);
    }

    public function test_cancelar_un_viaje_aceptado_libera_al_conductor(): void
    {
        // `active_driver_id` es una columna generada por la base (ver la
        // migración de `rides`): confirmar que se libera es lo que garantiza
        // que el conductor pueda aceptar otro viaje después.
        $pasajero = User::factory()->create();
        $conductor = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'active_driver_id' => null,
        ]);
    }

    #[DataProvider('estadosNoCancelables')]
    public function test_rechaza_cancelar_un_viaje_que_no_esta_requested_ni_accepted(RideStatus $estado): void
    {
        // Es 422 y no 403: el pasajero sigue siendo dueño del viaje, lo que
        // cambia es que ya no hay ningún flujo de cancelación que aplique
        // para ese punto del ciclo de vida.
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
    public static function estadosNoCancelables(): array
    {
        $noCancelables = [RideStatus::InProgress, RideStatus::Completed, RideStatus::Cancelled];

        return array_reduce(
            $noCancelables,
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

    public function test_el_conductor_asignado_cancela_su_viaje_aceptado_y_vuelve_al_pool(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.id', $viaje->id);
        $respuesta->assertJsonPath('data.status', 'requested');
        $respuesta->assertJsonMissingPath('data.cancellation_fee_applies');

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Requested->value,
            'driver_id' => null,
        ]);
    }

    public function test_cancelar_como_conductor_libera_al_conductor_para_aceptar_otro_viaje(): void
    {
        // `active_driver_id` es una columna generada por la base (ver la
        // migración de `rides`): confirmar que se libera es lo que garantiza
        // que el conductor pueda aceptar otro viaje después.
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'active_driver_id' => null,
        ]);
    }

    public function test_el_conductor_asignado_no_puede_cancelar_un_viaje_en_curso_con_este_endpoint(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ride');

        $this->assertDatabaseHas('rides', ['id' => $viaje->id, 'status' => RideStatus::InProgress->value]);
    }

    public function test_rechaza_cancelar_como_un_conductor_que_no_es_el_asignado(): void
    {
        $asignado = User::factory()->driver()->create();
        $otro = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $asignado->id,
        ]);

        $this->withToken(JWTAuth::fromUser($otro))
            ->postJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', ['id' => $viaje->id, 'status' => RideStatus::Accepted->value, 'driver_id' => $asignado->id]);
    }

    public function test_cancelar_repetidamente_como_conductor_incrementa_su_conteo_sin_bloquear(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id, 'cancellation_count' => 0]);

        $primerViaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);
        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($primerViaje))
            ->assertOk();

        // El conductor queda libre (el primer viaje volvió al pool), así que
        // puede aceptar y cancelar un segundo viaje sin que nada lo bloquee.
        $segundoViaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);
        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($segundoViaje))
            ->assertOk();

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $conductor->id,
            'cancellation_count' => 2,
        ]);
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
