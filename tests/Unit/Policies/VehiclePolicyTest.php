<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Policy invocada directo, sin pasar por HTTP.
 *
 * `PATCH /me/vehicle` no lleva id en la ruta, así que hoy no existe una request
 * que le pase a la Policy el vehículo de otra persona. Eso hace que el caso
 * "conductor que intenta editar la moto ajena" sea inalcanzable por HTTP y solo
 * verificable acá — y por eso la garantía se escribe en la Policy y no en el
 * hecho de que la ruta no tenga id: si el vehículo llega a direccionarse de otra
 * forma (un panel, un endpoint con id), la regla ya está puesta y no hay que
 * acordarse de agregarla.
 */
class VehiclePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_conductor_puede_registrar_un_vehiculo(): void
    {
        $this->assertTrue(User::factory()->driver()->create()->can('create', Vehicle::class));
    }

    public function test_el_pasajero_no_puede_registrar_un_vehiculo(): void
    {
        $this->assertFalse(User::factory()->create()->can('create', Vehicle::class));
    }

    public function test_el_conductor_puede_actualizar_su_propio_vehiculo(): void
    {
        $conductor = User::factory()->driver()->create();
        $vehiculo = Vehicle::factory()->create(['user_id' => $conductor->id]);

        $this->assertTrue($conductor->can('update', $vehiculo));
    }

    public function test_el_conductor_no_puede_actualizar_el_vehiculo_de_otro_conductor(): void
    {
        $conductor = User::factory()->driver()->create();
        $ajeno = Vehicle::factory()->create(['user_id' => User::factory()->driver()]);

        $this->assertFalse($conductor->can('update', $ajeno));
    }

    public function test_el_pasajero_no_puede_actualizar_un_vehiculo_aunque_figure_como_dueno(): void
    {
        // La base no impide que una fila apunte a una cuenta de pasajero (un
        // rol degradado a mano, por ejemplo). Operar vehículos sigue siendo del
        // rol conductor, así que la propiedad sola no alcanza.
        $pasajero = User::factory()->create();
        $vehiculo = Vehicle::factory()->create(['user_id' => $pasajero->id]);

        $this->assertFalse($pasajero->can('update', $vehiculo));
    }

    public function test_el_conductor_puede_ver_su_propio_vehiculo(): void
    {
        $conductor = User::factory()->driver()->create();
        $vehiculo = Vehicle::factory()->create(['user_id' => $conductor->id]);

        $this->assertTrue($conductor->can('view', $vehiculo));
    }

    public function test_el_conductor_no_puede_ver_el_vehiculo_de_otro_conductor(): void
    {
        $conductor = User::factory()->driver()->create();
        $ajeno = Vehicle::factory()->create(['user_id' => User::factory()->driver()]);

        $this->assertFalse($conductor->can('view', $ajeno));
    }

    public function test_el_pasajero_no_puede_ver_un_vehiculo_aunque_figure_como_dueno(): void
    {
        $pasajero = User::factory()->create();
        $vehiculo = Vehicle::factory()->create(['user_id' => $pasajero->id]);

        $this->assertFalse($pasajero->can('view', $vehiculo));
    }
}
