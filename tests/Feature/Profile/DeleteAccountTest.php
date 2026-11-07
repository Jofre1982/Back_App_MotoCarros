<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Enums\DocumentType;
use App\Enums\ErrandStatus;
use App\Enums\RideStatus;
use App\Models\DeviceToken;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de DELETE /api/v1/me — ver openapi.yaml. Requisito de Google
 * Play: quien crea una cuenta tiene que poder borrarla, con sus datos.
 */
class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_borra_la_cuenta_del_pasajero_con_sus_viajes_y_su_foto(): void
    {
        $pasajero = User::factory()->create([
            'password' => Hash::make('secreto123'),
            'avatar_path' => 'avatars/yo.jpg',
        ]);
        Storage::disk('local')->put('avatars/yo.jpg', 'foto');
        Storage::disk('local')->put('errands/lista.jpg', 'foto');
        $viaje = Ride::factory()->create(['passenger_id' => $pasajero->id, 'status' => RideStatus::Completed]);
        $mandado = Errand::factory()->create([
            'passenger_id' => $pasajero->id, 'status' => ErrandStatus::Completed, 'photo_path' => 'errands/lista.jpg',
        ]);
        DeviceToken::factory()->create(['user_id' => $pasajero->id]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->deleteJson(self::URI, ['password' => 'secreto123'])
            ->assertNoContent();

        $this->assertModelMissing($pasajero);
        $this->assertModelMissing($viaje);
        $this->assertModelMissing($mandado);
        $this->assertDatabaseMissing('device_tokens', ['user_id' => $pasajero->id]);
        Storage::disk('local')->assertMissing('avatars/yo.jpg');
        Storage::disk('local')->assertMissing('errands/lista.jpg');
    }

    public function test_al_conductor_le_borra_documentos_y_vehiculo_pero_el_pasajero_conserva_su_viaje(): void
    {
        $conductor = User::factory()->driver()->create(['password' => Hash::make('secreto123')]);
        $perfil = DriverProfile::factory()->create(['user_id' => $conductor->id]);
        $vehiculo = Vehicle::factory()->create(['user_id' => $conductor->id]);
        Storage::disk('local')->put("driver-documents/{$perfil->id}/licencia.jpg", 'doc');
        $documento = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'type' => DocumentType::FotoVehiculo,
            'path' => "driver-documents/{$perfil->id}/licencia.jpg",
        ]);
        $viajeDeOtro = Ride::factory()->create(['driver_id' => $conductor->id, 'status' => RideStatus::Completed]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->deleteJson(self::URI, ['password' => 'secreto123'])
            ->assertNoContent();

        $this->assertModelMissing($conductor);
        $this->assertModelMissing($perfil);
        $this->assertModelMissing($vehiculo);
        $this->assertModelMissing($documento);
        Storage::disk('local')->assertMissing("driver-documents/{$perfil->id}/licencia.jpg");
        $this->assertNull($viajeDeOtro->refresh()->driver_id);
    }

    public function test_el_token_deja_de_servir(): void
    {
        $pasajero = User::factory()->create(['password' => Hash::make('secreto123')]);
        $token = JWTAuth::fromUser($pasajero);

        $this->withToken($token)->deleteJson(self::URI, ['password' => 'secreto123'])->assertNoContent();

        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson(self::URI)->assertUnauthorized();
    }

    public function test_con_la_contrasena_equivocada_no_borra_nada(): void
    {
        $pasajero = User::factory()->create(['password' => Hash::make('secreto123')]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->deleteJson(self::URI, ['password' => 'otra'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->deleteJson(self::URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertModelExists($pasajero);
    }

    public function test_no_borra_con_un_viaje_o_pedido_sin_terminar(): void
    {
        $pasajero = User::factory()->create(['password' => Hash::make('secreto123')]);
        Ride::factory()->create(['passenger_id' => $pasajero->id, 'status' => RideStatus::Accepted]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->deleteJson(self::URI, ['password' => 'secreto123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account');

        $conductor = User::factory()->driver()->create(['password' => Hash::make('secreto123')]);
        Errand::factory()->create(['driver_id' => $conductor->id, 'status' => ErrandStatus::Accepted]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->deleteJson(self::URI, ['password' => 'secreto123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account');

        $this->assertModelExists($pasajero);
        $this->assertModelExists($conductor);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->deleteJson(self::URI, ['password' => 'x'])->assertUnauthorized();
    }
}
