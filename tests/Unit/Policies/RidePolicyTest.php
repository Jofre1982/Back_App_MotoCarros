<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Policy invocada directo, sin pasar por HTTP.
 *
 * Solicitar un viaje es del rol pasajero: el conductor consigue viajes
 * aceptando los que ya existen (historia #18), no creándolos.
 */
class RidePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pasajero_puede_solicitar_un_viaje(): void
    {
        $this->assertTrue(User::factory()->create()->can('create', Ride::class));
    }

    public function test_el_conductor_no_puede_solicitar_un_viaje(): void
    {
        $this->assertFalse(User::factory()->driver()->create()->can('create', Ride::class));
    }

    public function test_el_pasajero_dueno_puede_cancelar_su_viaje(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create();

        $this->assertTrue($pasajero->can('cancel', $viaje));
    }

    public function test_otro_pasajero_no_puede_cancelar_un_viaje_ajeno(): void
    {
        $viaje = Ride::factory()->create();

        $this->assertFalse(User::factory()->create()->can('cancel', $viaje));
    }

    public function test_un_conductor_no_puede_cancelar_un_viaje_aunque_la_fila_apunte_a_su_cuenta(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->for($conductor, 'passenger')->create();

        $this->assertFalse($conductor->can('cancel', $viaje));
    }

    public function test_el_conductor_puede_aceptar_un_viaje(): void
    {
        $this->assertTrue(User::factory()->driver()->create()->can('accept', Ride::class));
    }

    public function test_el_pasajero_no_puede_aceptar_un_viaje(): void
    {
        $this->assertFalse(User::factory()->create()->can('accept', Ride::class));
    }

    public function test_el_conductor_asignado_puede_iniciar_su_viaje(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['driver_id' => $conductor->id]);

        $this->assertTrue($conductor->can('start', $viaje));
    }

    public function test_otro_conductor_no_puede_iniciar_un_viaje_ajeno(): void
    {
        $viaje = Ride::factory()->create(['driver_id' => User::factory()->driver()]);

        $this->assertFalse(User::factory()->driver()->create()->can('start', $viaje));
    }

    public function test_nadie_puede_iniciar_un_viaje_sin_conductor_asignado(): void
    {
        $viaje = Ride::factory()->create(['driver_id' => null]);

        $this->assertFalse(User::factory()->driver()->create()->can('start', $viaje));
    }

    public function test_el_pasajero_no_puede_iniciar_su_propio_viaje(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create(['driver_id' => User::factory()->driver()]);

        $this->assertFalse($pasajero->can('start', $viaje));
    }

    public function test_el_pasajero_dueno_puede_ver_su_viaje(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create();

        $this->assertTrue($pasajero->can('view', $viaje));
    }

    public function test_el_conductor_asignado_puede_ver_el_viaje(): void
    {
        $conductor = User::factory()->driver()->create();
        $viaje = Ride::factory()->create(['driver_id' => $conductor->id]);

        $this->assertTrue($conductor->can('view', $viaje));
    }

    public function test_un_tercero_no_puede_ver_el_viaje(): void
    {
        $viaje = Ride::factory()->create(['driver_id' => User::factory()->driver()]);

        $this->assertFalse(User::factory()->create()->can('view', $viaje));
        $this->assertFalse(User::factory()->driver()->create()->can('view', $viaje));
    }

    /**
     * Ver el viaje **no** comprueba el rol además de la propiedad, al revés
     * que cancelar o iniciar. Es a propósito: allá se decide si esa cuenta
     * puede *operar* el viaje, y una cuenta que cambió de rol no debería poder
     * hacerlo; acá solo se lee lo que esa misma persona ya vivió, y quitarle
     * el acceso a su propio historial por un cambio de rol sería un efecto
     * secundario, no una regla que alguien haya pedido.
     */
    public function test_quien_participo_del_viaje_lo_sigue_viendo_aunque_cambie_de_rol(): void
    {
        $pasajero = User::factory()->create();
        $viaje = Ride::factory()->for($pasajero, 'passenger')->create();

        $pasajero->update(['role' => UserRole::Driver]);

        $this->assertTrue($pasajero->fresh()->can('view', $viaje));
    }
}
