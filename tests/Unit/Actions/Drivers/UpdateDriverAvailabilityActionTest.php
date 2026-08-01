<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Drivers;

use App\Actions\Drivers\UpdateDriverAvailabilityAction;
use App\DTOs\Coordinates;
use App\DTOs\DriverAvailabilityUpdate;
use App\Models\DriverProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class UpdateDriverAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marca_disponible_y_guarda_la_ubicacion(): void
    {
        $perfil = DriverProfile::factory()->create();

        $actualizado = $this->action()->handle($perfil, new DriverAvailabilityUpdate(
            isAvailable: true,
            location: new Coordinates(4.710989, -74.072092),
        ));

        $this->assertTrue($actualizado->is_available);
        $this->assertSame(4.710989, $actualizado->latitude);
        $this->assertSame(-74.072092, $actualizado->longitude);
        $this->assertNotNull($actualizado->location_updated_at);
    }

    public function test_marca_no_disponible_sin_tocar_la_ubicacion_si_no_llega_ninguna(): void
    {
        $perfil = DriverProfile::factory()->available()->create();
        $ubicacionPrevia = $perfil->location_updated_at;

        $actualizado = $this->action()->handle($perfil, new DriverAvailabilityUpdate(
            isAvailable: false,
            location: null,
        ));

        $this->assertFalse($actualizado->is_available);
        $this->assertSame(4.710989, $actualizado->latitude);
        $this->assertSame(-74.072092, $actualizado->longitude);
        $this->assertNotNull($ubicacionPrevia);
        $this->assertTrue($ubicacionPrevia->equalTo($actualizado->location_updated_at));
    }

    public function test_actualiza_la_ubicacion_de_un_conductor_ya_disponible(): void
    {
        $perfil = DriverProfile::factory()->available(latitude: 4.6, longitude: -74.1)->create();

        $actualizado = $this->action()->handle($perfil, new DriverAvailabilityUpdate(
            isAvailable: true,
            location: new Coordinates(4.71, -74.07),
        ));

        $this->assertSame(4.71, $actualizado->latitude);
        $this->assertSame(-74.07, $actualizado->longitude);
    }

    private function action(): UpdateDriverAvailabilityAction
    {
        return $this->app->make(UpdateDriverAvailabilityAction::class);
    }
}
