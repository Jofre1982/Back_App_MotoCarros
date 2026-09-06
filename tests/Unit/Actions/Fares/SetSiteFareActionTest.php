<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Fares;

use App\Actions\Fares\SetSiteFareAction;
use App\DTOs\SiteFareUpdate;
use App\Enums\PricingUnit;
use App\Enums\VehicleType;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetSiteFareActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_precio_si_no_existia(): void
    {
        $sitio = Site::factory()->create();

        $resultado = (new SetSiteFareAction)->handle($sitio, new SiteFareUpdate(
            vehicleType: VehicleType::Motocarro,
            pricingUnit: PricingUnit::PerPerson,
            dayPrice: 4000,
            nightPrice: 5000,
        ));

        $this->assertSame(4000, $resultado->day_price);
        $this->assertSame(5000, $resultado->night_price);
        $this->assertSame(1, $sitio->fares()->count());
    }

    public function test_reemplaza_el_precio_existente_del_mismo_tipo_de_vehiculo(): void
    {
        $sitio = Site::factory()->create();
        $action = new SetSiteFareAction;

        $action->handle($sitio, new SiteFareUpdate(VehicleType::Motocarro, PricingUnit::PerTrip, 10000, null));
        $resultado = $action->handle($sitio, new SiteFareUpdate(VehicleType::Motocarro, PricingUnit::PerTrip, 20000, null));

        $this->assertSame(20000, $resultado->day_price);
        $this->assertSame(1, $sitio->fares()->count());
    }
}
