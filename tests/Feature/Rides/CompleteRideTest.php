<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RecordsBroadcasts;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/rides/{id}/complete — ver openapi.yaml.
 *
 * Historia #24: el conductor que ya inició el viaje lo marca como
 * completado al llegar al destino. Cierra el ciclo de vida del viaje y
 * recalcula la tarifa final con el trayecto realmente recorrido. También
 * dispara su cobro (historia #25, `ChargeRideAction`), publicado en
 * `data.payment`.
 *
 * Completar publica el cambio de estado hacia el pasajero (historia #21); lo
 * que sale por el canal se prueba en `RideStatusChangedTest`. Acá el trait
 * está solo para que las requests que lo disparan no terminen buscando un
 * Reverb real.
 */
class CompleteRideTest extends TestCase
{
    use RecordsBroadcasts, RefreshDatabase;

    public function test_el_conductor_asignado_completa_su_viaje_en_curso(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = $this->viajeEnCursoPor($conductor);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.id', $viaje->id);
        $respuesta->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::Completed->value,
            'driver_id' => $conductor->id,
        ]);
    }

    public function test_publica_el_cobro_del_viaje_al_completarlo(): void
    {
        // Sin un proveedor de pago real configurado (historia #25, "fuera de
        // alcance"), el cobro resuelto por `NullPaymentGateway` siempre se
        // confirma.
        $conductor = User::factory()->driver()->create();
        $viaje = $this->viajeEnCursoPor($conductor);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertOk();

        $respuesta->assertJsonPath('data.payment.status', 'paid');
        $this->assertDatabaseHas('payments', [
            'ride_id' => $viaje->id,
            'status' => 'paid',
        ]);
    }

    public function test_registra_la_hora_de_finalizacion(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = $this->viajeEnCursoPor($conductor);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertOk();

        // La hora de finalización es un criterio de aceptación por sí misma,
        // y el contrato la publica: sin ella no hay forma de saber desde
        // cuándo terminó el viaje (para el recibo, historia #26).
        $this->assertNotNull($respuesta->json('data.completed_at'));
        $this->assertNotNull($viaje->refresh()->completed_at);
    }

    public function test_recalcula_la_tarifa_final_con_el_trayecto_realmente_recorrido(): void
    {
        Carbon::setTestNow('2026-07-31 14:09:05');
        $conductor = User::factory()->driver()->create();
        $viaje = $this->viajeEnCursoPor($conductor, distanciaMetros: 7421);

        // 600 segundos de viaje real, distintos de los 842 estimados al
        // pedirlo: lo que se cobra tiene que salir de este número, no de
        // `estimated_fare`.
        Carbon::setTestNow('2026-07-31 14:19:05');

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($viaje))
            ->assertOk();

        // base 1500 + distancia round(7421*800/1000)=5937 + tiempo
        // round(600*100/60)=1000 = 8437, redondeado hacia arriba a 8450.
        $respuesta->assertJsonPath('data.final_fare', 8450);
        $this->assertSame(8450, $viaje->refresh()->final_fare);
    }

    public function test_el_viaje_aun_no_completado_publica_completed_at_y_final_fare_nulos(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::Accepted,
            'driver_id' => $conductor->id,
        ]);

        // El campo está siempre presente, no solo cuando el viaje ya
        // terminó: así el cliente no tiene que distinguir "todavía no
        // terminó" de "esta respuesta no lo trae" (ver el schema `Ride` en
        // openapi.yaml). La estructura se afirma aparte del valor a
        // propósito: `assertJsonPath` contra `null` también pasa si la clave
        // no viene, que es justo el caso que este test existe para
        // descartar.
        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/rides/{$viaje->id}/start")
            ->assertOk();

        $respuesta->assertJsonStructure(['data' => ['completed_at', 'final_fare', 'payment']]);
        $respuesta->assertJsonPath('data.completed_at', null);
        $respuesta->assertJsonPath('data.final_fare', null);
        $respuesta->assertJsonPath('data.payment', null);
    }

    public function test_rechaza_a_un_conductor_que_no_es_el_asignado(): void
    {
        $asignado = User::factory()->driver()->create();
        $otroConductor = User::factory()->driver()->create();
        $viaje = $this->viajeEnCursoPor($asignado);

        $this->withToken(JWTAuth::fromUser($otroConductor))
            ->postJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::InProgress->value,
            'completed_at' => null,
        ]);
    }

    public function test_rechaza_al_pasajero_del_viaje(): void
    {
        $conductor = User::factory()->driver()->create();
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
            'started_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($viaje))
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::InProgress->value,
        ]);
    }

    #[DataProvider('estadosQueNoSePuedenCompletar')]
    public function test_rechaza_un_viaje_que_no_esta_en_curso(RideStatus $estado): void
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
        // Sin conductor asignado no hay a quién dejarlo completar, así que la
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
            ->postJson('/api/v1/rides/999999/complete')
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $viaje = $this->viajeEnCursoPor(User::factory()->driver()->create());

        $this->postJson($this->uri($viaje))
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'status' => RideStatus::InProgress->value,
            'completed_at' => null,
        ]);
    }

    private function viajeEnCursoPor(User $conductor, int $distanciaMetros = 7421): Ride
    {
        return Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
            'estimated_distance_meters' => $distanciaMetros,
            'started_at' => now(),
        ]);
    }

    private function uri(Ride $viaje): string
    {
        return "/api/v1/rides/{$viaje->id}/complete";
    }

    /**
     * El criterio de aceptación #3 nombra la transición inválida en general;
     * `accepted` (todavía no arrancó), `completed` y `cancelled` cubren el
     * resto del ciclo de vida. `requested` queda fuera de este conjunto a
     * propósito: ahí no hay conductor asignado y la respuesta correcta es
     * 403, con su propio test.
     *
     * @return array<string, array{RideStatus}>
     */
    public static function estadosQueNoSePuedenCompletar(): array
    {
        return [
            RideStatus::Accepted->value => [RideStatus::Accepted],
            RideStatus::Completed->value => [RideStatus::Completed],
            RideStatus::Cancelled->value => [RideStatus::Cancelled],
        ];
    }
}
