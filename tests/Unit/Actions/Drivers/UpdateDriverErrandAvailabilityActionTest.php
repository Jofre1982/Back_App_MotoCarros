<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Drivers;

use App\Actions\Drivers\UpdateDriverErrandAvailabilityAction;
use App\Models\DriverProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class UpdateDriverErrandAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_actualiza_la_disponibilidad_para_mandados_sin_tocar_la_de_viajes(): void
    {
        $perfil = DriverProfile::factory()->available()->create();

        $resultado = $this->app->make(UpdateDriverErrandAvailabilityAction::class)->handle($perfil, true);

        $this->assertTrue($resultado->is_available_for_errands);
        // La disponibilidad de viajes (`available()` la dejó en `true`) no
        // cambia: son pools independientes (historia #92).
        $this->assertTrue($resultado->fresh()->is_available);
    }
}
