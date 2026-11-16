<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Console\Commands\ResetDataCommand;
use App\Models\Ad;
use App\Models\CargoJobType;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `motoya:reiniciar-datos`: dejar la base desde cero antes de salir a
 * producción, conservando a los administradores y la configuración.
 */
class ResetDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path(ResetDataCommand::FLAG));
        @unlink(storage_path(ResetDataCommand::RESULT));
        parent::tearDown();
    }

    public function test_borra_todo_menos_administradores_y_configuracion(): void
    {
        $admin = User::factory()->admin()->create(['avatar_path' => 'avatars/admin.jpg']);
        Storage::disk('local')->put('avatars/admin.jpg', 'x');
        Storage::disk('local')->put('avatars/otro.jpg', 'x');
        Storage::disk('local')->put('driver-documents/1/cedula.jpg', 'x');
        Storage::disk('local')->put('errand-photos/a.jpg', 'x');

        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);
        Vehicle::factory()->create(['user_id' => $conductor->id]);
        Ride::factory()->create(['passenger_id' => $pasajero->id]);
        Errand::factory()->create(['passenger_id' => $pasajero->id]);
        $pasajero->favoriteDrivers()->attach($conductor->id);
        Ad::factory()->create();
        $sitios = Site::query()->count();
        CargoJobType::factory()->create();

        $this->artisan('motoya:reiniciar-datos', ['--force' => true])->assertSuccessful();

        $this->assertSame([$admin->id], User::query()->pluck('id')->all());
        $this->assertSame(0, Ride::query()->count());
        $this->assertSame(0, Errand::query()->count());
        $this->assertSame(0, DriverProfile::query()->count());
        $this->assertSame(0, Vehicle::query()->count());
        $this->assertSame(0, Ad::query()->count());
        $this->assertDatabaseCount('favorite_drivers', 0);
        $this->assertSame($sitios, Site::query()->count());
        $this->assertSame(1, CargoJobType::query()->count());

        Storage::disk('local')->assertExists('avatars/admin.jpg');
        Storage::disk('local')->assertMissing('avatars/otro.jpg');
        Storage::disk('local')->assertMissing('driver-documents/1/cedula.jpg');
        Storage::disk('local')->assertMissing('errand-photos/a.jpg');
    }

    public function test_desde_el_deploy_no_hace_nada_sin_la_orden(): void
    {
        User::factory()->create();

        $this->artisan('motoya:reiniciar-datos', ['--si-hay-orden' => true])->assertSuccessful();

        $this->assertSame(1, User::query()->count());
    }

    public function test_desde_el_deploy_con_la_orden_borra_una_sola_vez(): void
    {
        User::factory()->create();
        file_put_contents(storage_path(ResetDataCommand::FLAG), '');

        $this->artisan('motoya:reiniciar-datos', ['--si-hay-orden' => true])->assertSuccessful();

        $this->assertSame(0, User::query()->count());
        $this->assertFileDoesNotExist(storage_path(ResetDataCommand::FLAG));
        $this->assertFileExists(storage_path(ResetDataCommand::RESULT));

        User::factory()->create();
        $this->artisan('motoya:reiniciar-datos', ['--si-hay-orden' => true])->assertSuccessful();
        $this->assertSame(1, User::query()->count());
    }
}
