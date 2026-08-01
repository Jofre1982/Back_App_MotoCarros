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
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class CompleteRideActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('fares.currency', 'COP');
        Config::set('fares.base', 1500);
        Config::set('fares.per_kilometer', 800);
        Config::set('fares.per_minute', 100);
        Config::set('fares.per_waiting_minute', 60);
        Config::set('fares.minimum', 3000);
        Config::set('fares.rounding_step', 50);
    }

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

    public function test_recalcula_la_tarifa_con_la_distancia_estimada_y_la_duracion_real(): void
    {
        Carbon::setTestNow('2026-07-31 14:09:05');
        $viaje = $this->viajeEnCurso(distanciaMetros: 7421);

        // 600 segundos reales, no los 842 estimados al pedir el viaje.
        Carbon::setTestNow('2026-07-31 14:19:05');

        $this->app->make(CompleteRideAction::class)->handle($viaje);

        // base 1500 + distancia round(7421*800/1000)=5937 + tiempo
        // round(600*100/60)=1000 = 8437, redondeado hacia arriba a 8450.
        $this->assertSame(8450, $viaje->refresh()->final_fare);
    }

    public function test_una_distancia_recorrida_distinta_cambia_la_tarifa(): void
    {
        Carbon::setTestNow('2026-07-31 14:09:05');
        $viaje = $this->viajeEnCurso(distanciaMetros: 1000);

        Carbon::setTestNow('2026-07-31 14:11:05'); // 120 segundos despues

        $this->app->make(CompleteRideAction::class)->handle($viaje);

        // base 1500 + distancia round(1000*800/1000)=800 + tiempo
        // round(120*100/60)=200 = 2500, bajo el mínimo (3000): se aplica el
        // piso y se redondea a 3000.
        $this->assertSame(3000, $viaje->refresh()->final_fare);
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
        $viaje = $this->viajeEnCurso(distanciaMetros: 1000);

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
        $viaje = $this->viajeEnCurso(distanciaMetros: 1000);

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

    private function viajeEnCurso(int $distanciaMetros = 7421): Ride
    {
        return Ride::factory()->create([
            'status' => RideStatus::InProgress,
            'driver_id' => User::factory()->driver(),
            'estimated_distance_meters' => $distanciaMetros,
            'started_at' => now(),
        ]);
    }
}
