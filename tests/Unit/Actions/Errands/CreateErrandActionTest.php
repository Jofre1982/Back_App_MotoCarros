<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Errands;

use App\Actions\Errands\CreateErrandAction;
use App\DTOs\Coordinates;
use App\DTOs\ErrandRequest;
use App\Enums\ErrandStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La Action invocada directo, sin pasar por HTTP (ver .claude/STANDARDS.md).
 */
class CreateErrandActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_crea_el_mandado_en_requested_sin_conductor_ni_precio(): void
    {
        $pasajero = User::factory()->create();
        $sitio = Site::factory()->create();
        $entrada = new ErrandRequest(
            description: 'Recoger un paquete en la farmacia.',
            origin: new Coordinates(4.710989, -74.072092),
            destinationSiteId: $sitio->id,
        );

        $mandado = $this->app->make(CreateErrandAction::class)->handle($pasajero, $entrada);

        $this->assertSame(ErrandStatus::Requested, $mandado->status);
        $this->assertSame($pasajero->getKey(), $mandado->passenger_id);
        $this->assertNull($mandado->driver_id);
        $this->assertNull($mandado->agreed_price);
        $this->assertNull($mandado->photo_path);
        $this->assertSame($sitio->id, $mandado->destination_site_id);
    }

    public function test_guarda_la_foto_en_el_disco_privado_cuando_viene(): void
    {
        $pasajero = User::factory()->create();
        $sitio = Site::factory()->create();
        $foto = UploadedFile::fake()->create('paquete.jpg', 100, 'image/jpeg');
        $entrada = new ErrandRequest(
            description: 'Recoger un paquete en la farmacia.',
            origin: new Coordinates(4.710989, -74.072092),
            destinationSiteId: $sitio->id,
            photo: $foto,
        );

        $mandado = $this->app->make(CreateErrandAction::class)->handle($pasajero, $entrada);

        $this->assertNotNull($mandado->photo_path);
        $this->assertStringStartsWith('errand-photos/', $mandado->photo_path);
        Storage::disk('local')->assertExists($mandado->photo_path);
    }
}
