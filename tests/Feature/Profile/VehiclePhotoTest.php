<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\RideStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/users/{id}/vehicle-photo y de
 * `driver.vehicle.photo_url` — ver openapi.yaml.
 *
 * Pedido del dueño: junto a la foto del conductor, el pasajero ve la foto
 * del vehículo (color y características) para reconocerlo en la calle. Es el
 * documento `foto_vehiculo` y solo se publica aprobado por el admin.
 */
class VehiclePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_el_pasajero_ve_la_foto_del_vehiculo_de_su_conductor(): void
    {
        $pasajero = User::factory()->create();
        $conductor = $this->conductorConFoto(aprobada: true);
        $viaje = Ride::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => RideStatus::Accepted,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertOk()
            ->assertJsonPath('data.driver.vehicle.photo_url', route('users.vehicle-photo', $conductor));

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->get("/api/v1/users/{$conductor->id}/vehicle-photo")
            ->assertOk();
    }

    public function test_tambien_en_un_mandado(): void
    {
        $pasajero = User::factory()->create();
        $conductor = $this->conductorConFoto(aprobada: true);
        Errand::factory()->create(['passenger_id' => $pasajero->id, 'driver_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->get("/api/v1/users/{$conductor->id}/vehicle-photo")
            ->assertOk();
    }

    public function test_sin_aprobar_no_se_publica(): void
    {
        $pasajero = User::factory()->create();
        $conductor = $this->conductorConFoto(aprobada: false);
        $viaje = Ride::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => RideStatus::Accepted,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertJsonPath('data.driver.vehicle.photo_url', null);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->get("/api/v1/users/{$conductor->id}/vehicle-photo")
            ->assertNotFound();
    }

    public function test_quien_no_comparte_un_servicio_no_la_ve(): void
    {
        $conductor = $this->conductorConFoto(aprobada: true);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->get("/api/v1/users/{$conductor->id}/vehicle-photo")
            ->assertForbidden();
    }

    public function test_el_conductor_ve_la_suya(): void
    {
        $conductor = $this->conductorConFoto(aprobada: true);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->get("/api/v1/users/{$conductor->id}/vehicle-photo")
            ->assertOk();
    }

    public function test_sin_token_responde_401(): void
    {
        $conductor = $this->conductorConFoto(aprobada: true);

        $this->getJson("/api/v1/users/{$conductor->id}/vehicle-photo")->assertUnauthorized();
    }

    private function conductorConFoto(bool $aprobada): User
    {
        $conductor = User::factory()->driver()->create();
        $perfil = DriverProfile::factory()->create(['user_id' => $conductor->id]);
        Vehicle::factory()->create(['user_id' => $conductor->id]);
        $path = UploadedFile::fake()->image('moto.jpg')->store("driver-documents/{$perfil->id}", 'local');
        $documento = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'type' => DocumentType::FotoVehiculo,
            'path' => $path,
        ]);
        if ($aprobada) {
            $documento->update(['status' => DocumentStatus::Approved, 'reviewed_at' => now()]);
        }

        return $conductor;
    }
}
