<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payments;

use App\Actions\Payments\ChargeRideAction;
use App\DTOs\FareBreakdown;
use App\Enums\PaymentStatus;
use App\Enums\RideStatus;
use App\Exceptions\PaymentProcessingFailed;
use App\Models\Payment;
use App\Models\Ride;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class ChargeRideActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_pago_pagado_con_el_monto_el_desglose_y_la_moneda_del_viaje(): void
    {
        $viaje = $this->viajeCompletado(montoFinal: 8450, moneda: 'COP');
        $desglose = $this->desglose(moneda: 'COP', total: 8450);

        $pago = $this->action()->handle($viaje, $desglose);

        $this->assertSame($viaje->id, $pago->ride_id);
        $this->assertSame(8450, $pago->amount);
        $this->assertSame('COP', $pago->currency);
        $this->assertSame($desglose->base, $pago->base_fare);
        $this->assertSame($desglose->distance, $pago->distance_fare);
        $this->assertSame($desglose->time, $pago->time_fare);
        $this->assertSame($desglose->waiting, $pago->waiting_fee);
        $this->assertSame($desglose->subtotal, $pago->subtotal);
        $this->assertSame($desglose->minimumApplied, $pago->minimum_applied);
        $this->assertSame(PaymentStatus::Paid, $pago->status);
        $this->assertNotNull($pago->processed_at);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_marca_el_pago_como_failed_si_el_proveedor_rechaza_el_cobro(): void
    {
        $viaje = $this->viajeCompletado();

        $gateway = new class implements PaymentGateway
        {
            public function charge(Ride $ride): void
            {
                throw PaymentProcessingFailed::rejectedByProvider('fake', 'fondos insuficientes');
            }
        };
        $this->app->instance(PaymentGateway::class, $gateway);

        $pago = $this->app->make(ChargeRideAction::class)->handle($viaje, $this->desglose());

        $this->assertSame(PaymentStatus::Failed, $pago->status);
        $this->assertNull($pago->processed_at);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_un_fallo_del_proveedor_no_bloquea_que_se_registre_el_pago(): void
    {
        // Mismo caso que el test anterior, visto desde el ángulo del criterio
        // de aceptación: la Action no lanza, siempre devuelve un `Payment`.
        $viaje = $this->viajeCompletado();

        $gateway = new class implements PaymentGateway
        {
            public function charge(Ride $ride): void
            {
                throw PaymentProcessingFailed::providerUnreachable('fake', 'timeout');
            }
        };
        $this->app->instance(PaymentGateway::class, $gateway);

        $pago = $this->app->make(ChargeRideAction::class)->handle($viaje, $this->desglose());

        $this->assertInstanceOf(Payment::class, $pago);
    }

    public function test_un_viaje_con_pago_existente_no_genera_un_segundo_cobro(): void
    {
        $viaje = $this->viajeCompletado();
        $pagoExistente = Payment::factory()->for($viaje)->create();

        $gateway = new class implements PaymentGateway
        {
            public bool $llamado = false;

            public function charge(Ride $ride): void
            {
                $this->llamado = true;
            }
        };
        $this->app->instance(PaymentGateway::class, $gateway);

        $pago = $this->app->make(ChargeRideAction::class)->handle($viaje->refresh(), $this->desglose());

        $this->assertFalse($gateway->llamado);
        $this->assertSame($pagoExistente->id, $pago->id);
        $this->assertDatabaseCount('payments', 1);
    }

    private function action(): ChargeRideAction
    {
        $this->app->instance(PaymentGateway::class, new class implements PaymentGateway
        {
            public function charge(Ride $ride): void {}
        });

        return $this->app->make(ChargeRideAction::class);
    }

    private function viajeCompletado(int $montoFinal = 8450, string $moneda = 'COP'): Ride
    {
        return Ride::factory()->create([
            'status' => RideStatus::Completed,
            'currency' => $moneda,
            'final_fare' => $montoFinal,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function desglose(string $moneda = 'COP', int $total = 8450): FareBreakdown
    {
        return new FareBreakdown(
            currency: $moneda,
            base: 1500,
            distance: 5937,
            time: 1000,
            waiting: 0,
            subtotal: 8437,
            total: $total,
            minimumApplied: false,
        );
    }
}
