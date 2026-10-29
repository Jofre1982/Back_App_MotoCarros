<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Enums\ErrandStatus;
use App\Enums\RideStatus;
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
 * Foto de perfil: POST /me/avatar y GET /users/{id}/avatar — ver
 * openapi.yaml. El pasajero tiene que poder reconocer a quien lo recoge.
 */
class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function cambiarDeCuenta(): void
    {
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();
    }

    private function subir(User $user, string $nombre = 'yo.jpg'): void
    {
        $this->withToken(JWTAuth::fromUser($user))
            ->post('/api/v1/me/avatar', ['photo' => UploadedFile::fake()->image($nombre, 400, 400)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.avatar_url', route('users.avatar', $user));
        $this->cambiarDeCuenta();
    }

    private function aprobar(User $user): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson("/api/v1/admin/users/{$user->id}/avatar/approve")
            ->assertOk()
            ->assertJsonPath('data.avatar_status', 'approved');
        $this->cambiarDeCuenta();
    }

    public function test_cualquier_cuenta_sube_su_foto_y_la_ve(): void
    {
        $pasajero = User::factory()->create();

        $this->subir($pasajero);
        $this->assertSame('approved', $pasajero->refresh()->avatar_status);

        $this->assertNotNull($pasajero->refresh()->avatar_path);
        Storage::disk('local')->assertExists($pasajero->avatar_path);
        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson('/api/v1/me')
            ->assertJsonPath('data.avatar_url', route('users.avatar', $pasajero));
        $this->withToken(JWTAuth::fromUser($pasajero))
            ->get("/api/v1/users/{$pasajero->id}/avatar")
            ->assertOk();
    }

    public function test_subir_otra_reemplaza_y_borra_la_anterior(): void
    {
        $pasajero = User::factory()->create();
        $this->subir($pasajero, 'uno.jpg');
        $anterior = $pasajero->refresh()->avatar_path;

        $this->subir($pasajero, 'dos.jpg');

        Storage::disk('local')->assertMissing($anterior);
        Storage::disk('local')->assertExists($pasajero->refresh()->avatar_path);
    }

    public function test_rechaza_algo_que_no_es_una_foto(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->post('/api/v1/me/avatar', ['photo' => UploadedFile::fake()->create('virus.exe', 10)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_el_pasajero_ve_la_foto_y_la_placa_del_conductor_de_su_viaje(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);
        Vehicle::factory()->create(['user_id' => $conductor->id, 'plate' => 'ABC12D']);
        $this->subir($conductor);
        $this->aprobar($conductor);
        $viaje = Ride::factory()->pickupOnly()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => RideStatus::Accepted,
            'agreed_price' => 7000,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertJsonPath('data.driver.avatar_url', route('users.avatar', $conductor))
            ->assertJsonPath('data.driver.vehicle', ['plate' => 'ABC12D', 'type' => 'motocarro']);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->get("/api/v1/users/{$conductor->id}/avatar")
            ->assertOk();
    }

    public function test_el_conductor_ve_la_foto_del_pasajero_de_su_mandado(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();
        $this->subir($pasajero);
        Errand::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => ErrandStatus::Accepted,
            'agreed_price' => 5000,
            'price_confirmed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson('/api/v1/me/errands?as=driver')
            ->assertJsonPath('data.0.counterpart.avatar_url', route('users.avatar', $pasajero));

        $this->withToken(JWTAuth::fromUser($conductor))
            ->get("/api/v1/users/{$pasajero->id}/avatar")
            ->assertOk();
    }

    public function test_un_desconocido_no_ve_la_foto(): void
    {
        $pasajero = User::factory()->create();
        $this->subir($pasajero);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson("/api/v1/users/{$pasajero->id}/avatar")
            ->assertForbidden();
    }

    public function test_sin_foto_responde_404(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/users/{$pasajero->id}/avatar")
            ->assertNotFound();
    }

    public function test_la_foto_de_un_conductor_queda_pendiente_y_el_pasajero_no_la_ve(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);
        $this->subir($conductor);
        $viaje = Ride::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => RideStatus::Accepted,
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson('/api/v1/me')
            ->assertJsonPath('data.avatar_status', 'pending');
        $this->cambiarDeCuenta();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson("/api/v1/rides/{$viaje->id}")
            ->assertJsonPath('data.driver.avatar_url', null);
        $this->withToken(JWTAuth::fromUser($pasajero))
            ->get("/api/v1/users/{$conductor->id}/avatar")
            ->assertForbidden();
    }

    public function test_el_admin_lista_rechaza_con_motivo_y_el_conductor_lo_ve(): void
    {
        $conductor = User::factory()->driver()->create();
        $this->subir($conductor);
        $admin = User::factory()->admin()->create();

        $this->withToken(JWTAuth::fromUser($admin))
            ->getJson('/api/v1/admin/avatars')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conductor->id)
            ->assertJsonPath('data.0.avatar_status', 'pending');

        $this->withToken(JWTAuth::fromUser($admin))
            ->postJson("/api/v1/admin/users/{$conductor->id}/avatar/reject", ['reason' => 'No se ve la cara.'])
            ->assertOk()
            ->assertJsonPath('data.avatar_status', 'rejected');

        $this->withToken(JWTAuth::fromUser($admin))
            ->postJson("/api/v1/admin/users/{$conductor->id}/avatar/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
        $this->cambiarDeCuenta();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson('/api/v1/me')
            ->assertJsonPath('data.avatar_status', 'rejected')
            ->assertJsonPath('data.avatar_rejection_reason', 'No se ve la cara.');
    }

    public function test_rechazar_exige_motivo_y_solo_lo_hace_un_admin(): void
    {
        $conductor = User::factory()->driver()->create();
        $this->subir($conductor);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson("/api/v1/admin/users/{$conductor->id}/avatar/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
        $this->cambiarDeCuenta();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson("/api/v1/admin/users/{$conductor->id}/avatar/approve")
            ->assertForbidden();
    }

    public function test_un_pasajero_que_se_vuelve_conductor_vuelve_a_revision(): void
    {
        $pasajero = User::factory()->create();
        $this->subir($pasajero);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson('/api/v1/me/driver-profile', ['license_number' => 'ABC-12345'])
            ->assertCreated();

        $this->assertSame('pending', $pasajero->refresh()->avatar_status);
    }
}
