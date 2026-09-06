<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\CompleteRideAction;
use App\Enums\PaymentStatus;
use App\Enums\RideStatus;
use App\Exceptions\PaymentProcessingFailed;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class CompleteRideActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pasa_el_viaje_a_completed(): void
    {
        $viaje = $this->viajeEnCurso();

        $resultado = $this->app->make(CompleteRideAction::class)->handle($viaje);

        $this->assertSame(RideStatus::Completed, $resultado->status);
        $this->assertSame(RideStatus::Completed, $viaje->refresh()->status);
    }

    public function test_registra_la_hora_de_finalizacion(): void
    {
        Carbon::setTestNow('2026-07-31 14:09:05');
        $viaje = $this->viajeEnCurso();

        Carbon::setTestNow('2026-07-31 14:19:05');
        $this->app->make(CompleteRideAction::class)->handle($viaje);

        $this->assertNotNull($viaje->refresh()->completed_at);
        $this->assertTrue(Carbon::now()->equalTo($viaje->completed_at));
    }

    /**
     * Desde la historia #87 el precio es fijo por sitio: no hay nada que
     * recalcular con el trayecto realmente recorrido, `final_fare` siempre
     * queda igual a `estimated_fare`.
     */
    public function test_el_cobro_final_es_igual_al_estimado(): void
    {
        $viaje = $this->viajeEnCurso(tarifaEstimada: 20000);

        $this->app->make(CompleteRideAction::class)->handle($viaje);

        $this->assertSame(20000, $viaje->refresh()->final_fare);
    }

    /**
     * Aunque pase mucho tiempo entre `started_at` y que el conductor lo
     * complete, el cobro no cambia — a diferencia del motor por distancia
     * que reemplazó esta historia.
     */
    public function test_el_tiempo_transcurrido_no_cambia_el_cobro(): void
    {
        Carbon::setTestNow('2026-07-31 14:09:05');
        $viaje = $this->viajeEnCurso(tarifaEstimada: 4000);

        Carbon::setTestNow('2026-07-31 16:45:00');
        $this->app->make(CompleteRideAction::class)->handle($viaje);

        $this->assertSame(4000, $viaje->refresh()->final_fare);
    }

    public function test_no_toca_al_conductor_asignado(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => $conductor->id,
            'started_at' => now(),
        ]);

        $this->app->make(CompleteRideAction::class)->handle($viaje);

        $this->assertSame($conductor->getKey(), $viaje->refresh()->driver_id);
    }

    public function test_registra_el_cobro_del_viaje_al_completarlo(): void
    {
        // Sin bindear un fake, resuelve `NullPaymentGateway` (ver
        // AppServiceProvider), que da el cobro por exitoso.
        $viaje = $this->viajeEnCurso();

        $this->app->make(CompleteRideAction::class)->handle($viaje);

        $pago = Payment::query()->where('ride_id', $viaje->id)->firstOrFail();
        $this->assertSame(PaymentStatus::Paid, $pago->status);
        $this->assertSame($viaje->refresh()->final_fare, $pago->amount);
    }

    public function test_un_cobro_rechazado_no_impide_completar_el_viaje(): void
    {
        // Historia #25, criterio de aceptación: un fallo en el procesamiento
        // del cobro deja el viaje `completed` con el pago en `failed`, sin
        // bloquear la finalización.
        $viaje = $this->viajeEnCurso();

        $this->app->instance(PaymentGateway::class, new class implements PaymentGateway
        {
            public function charge(Ride $ride): void
            {
                throw PaymentProcessingFailed::rejectedByProvider('fake', 'fondos insuficientes');
            }
        });

        $resultado = $this->app->make(CompleteRideAction::class)->handle($viaje);

        $this->assertSame(RideStatus::Completed, $resultado->status);
        $this->assertSame(RideStatus::Completed, $viaje->refresh()->status);

        $pago = Payment::query()->where('ride_id', $viaje->id)->firstOrFail();
        $this->assertSame(PaymentStatus::Failed, $pago->status);
    }

    private function viajeEnCurso(int $tarifaEstimada = 7450): Ride
    {
        return Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => User::factory()->driver(),
            'estimated_fare' => $tarifaEstimada,
            'started_at' => now(),
        ]);
    }
}
