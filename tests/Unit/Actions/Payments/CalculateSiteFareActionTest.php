<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payments;

use App\Actions\Payments\CalculateSiteFareAction;
use App\Enums\PricingUnit;
use App\Enums\VehicleType;
use App\Models\Site;
use App\Models\SiteFare;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CalculateSiteFareActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('fares.currency', 'COP');
    }

    public function test_cobra_el_precio_por_viaje_sin_importar_los_pasajeros(): void
    {
        $sitio = $this->siteConTarifa(20000, PricingUnit::PerTrip);

        $resultado = $this->app->make(CalculateSiteFareAction::class)
            ->handle($sitio, VehicleType::Motocarro, 3);

        $this->assertSame(20000, $resultado->total);
        $this->assertSame('COP', $resultado->currency);
    }

    public function test_multiplica_el_precio_por_pasajero(): void
    {
        $sitio = $this->siteConTarifa(4000, PricingUnit::PerPerson);

        $resultado = $this->app->make(CalculateSiteFareAction::class)
            ->handle($sitio, VehicleType::Motocarro, 3);

        $this->assertSame(12000, $resultado->total);
    }

    public function test_el_desglose_no_tiene_conceptos_ademas_del_precio_fijo(): void
    {
        $sitio = $this->siteConTarifa(4000);

        $resultado = $this->app->make(CalculateSiteFareAction::class)
            ->handle($sitio, VehicleType::Motocarro, 1);

        $this->assertSame(4000, $resultado->base);
        $this->assertSame(0, $resultado->distance);
        $this->assertSame(0, $resultado->time);
        $this->assertSame(0, $resultado->waiting);
        $this->assertFalse($resultado->minimumApplied);
    }

    public function test_falla_si_el_sitio_no_tiene_precio_para_ese_tipo_de_vehiculo(): void
    {
        $sitio = Site::factory()->create();
        SiteFare::factory()->create([
            'site_id' => $sitio->id,
            'vehicle_type' => VehicleType::Motocarga,
            'day_price' => 20000,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->app->make(CalculateSiteFareAction::class)->handle($sitio, VehicleType::Motocarro, 1);
    }

    private function siteConTarifa(
        int $dayPrice,
        PricingUnit $pricingUnit = PricingUnit::PerTrip,
    ): Site {
        $site = Site::factory()->create();
        SiteFare::factory()->create([
            'site_id' => $site->id,
            'vehicle_type' => VehicleType::Motocarro,
            'pricing_unit' => $pricingUnit,
            'day_price' => $dayPrice,
        ]);

        return $site;
    }
}
