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

    public function test_un_pasajero_que_activo_el_modo_conductor_maneja_su_disponibilidad(): void
    {
        // Historia #99: una cuenta registrada como pasajero que tiene perfil
        // de conductor (lo activó con POST /me/driver-profile) es conductor.
        $pasajero = User::factory()->create();
        $perfil = DriverProfile::factory()->create(['user_id' => $pasajero->id]);

        $this->assertTrue($pasajero->can('updateAvailability', $perfil));
    }

    public function test_un_admin_no_maneja_la_disponibilidad_de_un_perfil(): void
    {
        $admin = User::factory()->admin()->create();
        $perfil = DriverProfile::factory()->create();

        $this->assertFalse($admin->can('updateAvailability', $perfil));
    }
}
