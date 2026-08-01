<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Realtime;

use App\DTOs\Coordinates;
use App\Models\DriverProfile;
use App\Services\Realtime\EloquentNearbyDriverFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentNearbyDriverFinderTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGEN = [4.710989, -74.072092];

    public function test_encuentra_un_conductor_disponible_dentro_del_radio(): void
    {
        $conductor = DriverProfile::factory()->available()->create();

        $ids = $this->finder(3000)->near(new Coordinates(...self::ORIGEN));

        $this->assertSame([$conductor->user_id], $ids);
    }

    public function test_ignora_un_conductor_no_disponible(): void
    {
        DriverProfile::factory()->create([
            'latitude' => self::ORIGEN[0],
            'longitude' => self::ORIGEN[1],
            'is_available' => false,
        ]);

        $this->assertSame([], $this->finder(3000)->near(new Coordinates(...self::ORIGEN)));
    }

    public function test_ignora_un_conductor_sin_ubicacion_conocida(): void
    {
        DriverProfile::factory()->create(['is_available' => true]);

        $this->assertSame([], $this->finder(3000)->near(new Coordinates(...self::ORIGEN)));
    }

    public function test_ignora_un_conductor_disponible_fuera_del_radio(): void
    {
        DriverProfile::factory()->available(latitude: 4.9, longitude: -74.3)->create();

        $this->assertSame([], $this->finder(3000)->near(new Coordinates(...self::ORIGEN)));
    }

    public function test_respeta_el_radio_configurado(): void
    {
        // ~1.1 km al norte del origen.
        $conductor = DriverProfile::factory()->available(latitude: 4.721, longitude: -74.072092)->create();

        $this->assertSame([], $this->finder(500)->near(new Coordinates(...self::ORIGEN)));
        $this->assertSame([$conductor->user_id], $this->finder(2000)->near(new Coordinates(...self::ORIGEN)));
    }

    public function test_devuelve_varios_conductores_cercanos(): void
    {
        $primero = DriverProfile::factory()->available()->create();
        $segundo = DriverProfile::factory()->available(latitude: 4.711, longitude: -74.0715)->create();

        $ids = $this->finder(3000)->near(new Coordinates(...self::ORIGEN));

        $this->assertEqualsCanonicalizing([$primero->user_id, $segundo->user_id], $ids);
    }

    private function finder(int $radiusMeters): EloquentNearbyDriverFinder
    {
        return new EloquentNearbyDriverFinder($radiusMeters);
    }
}
