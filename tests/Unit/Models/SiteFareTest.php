<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\SiteFare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `SiteFare::priceAt()` (historia técnica #85): el recargo nocturno es un
 * dato por sitio (`night_price`), no una regla de código específica para
 * "casco urbano" — un sitio sin `night_price` cobra siempre el precio de
 * día, sin importar la hora.
 */
class SiteFareTest extends TestCase
{
    use RefreshDatabase;

    public function test_cobra_el_precio_de_dia_antes_de_las_10pm(): void
    {
        $precio = SiteFare::factory()->make(['day_price' => 4000, 'night_price' => 5000]);

        $this->assertSame(4000, $precio->priceAt(Carbon::parse('2026-09-06 21:59')));
    }

    public function test_cobra_el_precio_de_noche_desde_las_10pm(): void
    {
        $precio = SiteFare::factory()->make(['day_price' => 4000, 'night_price' => 5000]);

        $this->assertSame(5000, $precio->priceAt(Carbon::parse('2026-09-06 22:00')));
        $this->assertSame(5000, $precio->priceAt(Carbon::parse('2026-09-06 23:30')));
    }

    public function test_un_sitio_sin_recargo_nocturno_siempre_cobra_el_precio_de_dia(): void
    {
        $precio = SiteFare::factory()->make(['day_price' => 6000, 'night_price' => null]);

        $this->assertSame(6000, $precio->priceAt(Carbon::parse('2026-09-06 23:30')));
    }
}
