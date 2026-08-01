<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Policy invocada directo, sin pasar por HTTP.
 *
 * `PATCH /me/availability` no lleva id en la ruta, así que hoy no existe una
 * request que le pase a la Policy el perfil de otra persona. Mismo criterio
 * que `VehiclePolicyTest`: el caso "conductor que intenta operar el perfil
 * ajeno" es inalcanzable por HTTP y solo verificable acá.
 */
class DriverProfilePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_conductor_puede_actualizar_su_propia_disponibilidad(): void
    {
        $conductor = User::factory()->driver()->create();
        $perfil = DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->assertTrue($conductor->can('updateAvailability', $perfil));
    }

    public function test_el_conductor_no_puede_actualizar_la_disponibilidad_de_otro_conductor(): void
    {
        $conductor = User::factory()->driver()->create();
        $ajeno = DriverProfile::factory()->create(['user_id' => User::factory()->driver()]);

        $this->assertFalse($conductor->can('updateAvailability', $ajeno));
    }

    public function test_el_pasajero_no_puede_actualizar_disponibilidad_aunque_figure_como_dueno(): void
    {
        // La base no impide que una fila apunte a una cuenta de pasajero (un
        // rol degradado a mano, por ejemplo). Operar disponibilidad sigue
        // siendo del rol conductor, así que la propiedad sola no alcanza.
        $pasajero = User::factory()->create();
        $perfil = DriverProfile::factory()->create(['user_id' => $pasajero->id]);

        $this->assertFalse($pasajero->can('updateAvailability', $perfil));
    }
}
