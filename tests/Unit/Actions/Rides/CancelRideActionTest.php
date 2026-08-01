<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rides;

use App\Actions\Rides\CancelRideAction;
use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver
 * .claude/STANDARDS.md).
 */
class CancelRideActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pasa_el_viaje_a_cancelled(): void
    {
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $resultado = $this->app->make(CancelRideAction::class)->handle($viaje, $viaje->passenger);

        $this->assertSame(RideStatus::Cancelled, $resultado->ride->status);
        $this->assertSame(RideStatus::Cancelled, $viaje->refresh()->status);
    }

    public function test_libera_el_slot_de_viaje_activo_del_pasajero(): void
    {
        // `active_passenger_id` es una columna generada por la base (ver la
        // migración de `rides`): confirmar que se libera es lo que garantiza
        // que el pasajero pueda volver a solicitar un viaje después.
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $this->app->make(CancelRideAction::class)->handle($viaje, $viaje->passenger);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'active_passenger_id' => null,
        ]);
    }

    public function test_cancelar_desde_requested_no_aplica_penalizacion(): void
    {
        $viaje = Ride::factory()->create(['status' => RideStatus::Requested]);

        $resultado = $this->app->make(CancelRideAction::class)->handle($viaje, $viaje->passenger);

        $this->assertFalse($resultado->feeApplies);
    }

    public function test_cancelar_desde_accepted_aplica_penalizacion(): void
    {
        $conductor = User::factory()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $resultado = $this->app->make(CancelRideAction::class)->handle($viaje, $viaje->passenger);

        $this->assertTrue($resultado->feeApplies);
    }

    public function test_cancelar_desde_accepted_libera_el_slot_de_viaje_activo_del_conductor(): void
    {
        // `active_driver_id` es una columna generada por la base (ver la
        // migración de `rides`): confirmar que se libera es lo que garantiza
        // que el conductor pueda aceptar otro viaje después.
        $conductor = User::factory()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $this->app->make(CancelRideAction::class)->handle($viaje, $viaje->passenger);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'active_driver_id' => null,
        ]);
    }

    public function test_cancelar_desde_accepted_conserva_el_conductor_como_registro_historico(): void
    {
        $conductor = User::factory()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $this->app->make(CancelRideAction::class)->handle($viaje, $viaje->passenger);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'driver_id' => $conductor->id,
        ]);
    }

    /**
     * Cuando quien cancela es el conductor asignado (historia #23), la
     * Action toma la otra rama: el viaje no se cancela, vuelve al pool.
     */
    public function test_el_conductor_asignado_devuelve_el_viaje_al_pool_en_vez_de_cancelarlo(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $resultado = $this->app->make(CancelRideAction::class)->handle($viaje, $conductor);

        $this->assertSame(RideStatus::Requested, $resultado->ride->status);
        $this->assertSame(RideStatus::Requested, $viaje->refresh()->status);
    }

    public function test_devolver_al_pool_deja_el_viaje_sin_conductor_asignado(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $this->app->make(CancelRideAction::class)->handle($viaje, $conductor);

        $this->assertNull($viaje->refresh()->driver_id);
    }

    public function test_devolver_al_pool_no_aplica_penalizacion(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $resultado = $this->app->make(CancelRideAction::class)->handle($viaje, $conductor);

        $this->assertFalse($resultado->feeApplies);
    }

    public function test_devolver_al_pool_libera_el_slot_de_viaje_activo_del_conductor(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $this->app->make(CancelRideAction::class)->handle($viaje, $conductor);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'active_driver_id' => null,
        ]);
    }

    public function test_devolver_al_pool_mantiene_al_pasajero_con_su_viaje_activo(): void
    {
        // El viaje sigue en pie para el pasajero: vuelve a `requested`, que
        // sigue siendo un estado activo, así que su slot no se libera.
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $this->app->make(CancelRideAction::class)->handle($viaje, $conductor);

        $this->assertDatabaseHas('rides', [
            'id' => $viaje->id,
            'active_passenger_id' => $viaje->passenger_id,
        ]);
    }

    public function test_devolver_al_pool_incrementa_el_conteo_de_cancelaciones_del_conductor(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id, 'cancellation_count' => 2]);
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $this->app->make(CancelRideAction::class)->handle($viaje, $conductor);

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $conductor->id,
            'cancellation_count' => 3,
        ]);
    }

    public function test_devolver_al_pool_no_falla_si_el_conductor_no_tiene_perfil(): void
    {
        // En producción todo conductor tiene perfil (se crea junto con la
        // cuenta, ver `RegisterDriverAction`); esto solo cubre que la Action
        // no explote si ese invariante no se cumpliera.
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $conductor->id]);

        $resultado = $this->app->make(CancelRideAction::class)->handle($viaje, $conductor);

        $this->assertSame(RideStatus::Requested, $resultado->ride->status);
    }
}
