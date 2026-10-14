<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\DriverVerificationStatus;
use App\Enums\ErrandStatus;
use App\Enums\RideStatus;
use App\Enums\VehicleType;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\Ride;
use App\Models\Site;
use App\Models\SiteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RecordsBroadcasts;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Una misma cuenta puede ser pasajero y conductor (historia #99).
 */
class DualRoleAccountTest extends TestCase
{
    use RecordsBroadcasts, RefreshDatabase;

    public function test_el_login_informa_los_roles_de_la_cuenta(): void
    {
        $pasajero = User::factory()->create(['password' => 'motoya2026']);
        $conductor = User::factory()->driver()->create(['password' => 'motoya2026']);
        DriverProfile::factory()->create(['user_id' => $conductor->id]);
        $admin = User::factory()->admin()->create(['password' => 'motoya2026']);

        $this->postJson('/api/v1/auth/login', ['email' => $pasajero->email, 'password' => 'motoya2026'])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'passenger')
            ->assertJsonPath('data.user.roles', ['passenger']);

        $this->postJson('/api/v1/auth/login', ['email' => $conductor->email, 'password' => 'motoya2026'])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'driver')
            ->assertJsonPath('data.user.roles', ['passenger', 'driver']);

        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'motoya2026'])
            ->assertOk()
            ->assertJsonPath('data.user.roles', ['admin']);
    }

    public function test_un_pasajero_activa_el_modo_conductor(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson('/api/v1/me/driver-profile', ['license_number' => ' abc-12345 '])
            ->assertCreated()
            ->assertJsonPath('data.id', $pasajero->id)
            ->assertJsonPath('data.role', 'passenger')
            ->assertJsonPath('data.roles', ['passenger', 'driver'])
            ->assertJsonPath('data.driver_profile.license_number', 'ABC-12345');

        $perfil = DriverProfile::query()->where('user_id', $pasajero->id)->sole();
        $this->assertSame(DriverVerificationStatus::Pending, $perfil->verification_status);
        $this->assertFalse($perfil->is_available);
    }

    public function test_despues_de_activarlo_puede_registrar_su_vehiculo(): void
    {
        $pasajero = User::factory()->create();
        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson('/api/v1/me/driver-profile', ['license_number' => 'ABC-12345'])
            ->assertCreated();

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($pasajero->refresh()))
            ->postJson('/api/v1/me/vehicle', [
                'plate' => 'ABC12D',
                'type' => VehicleType::Motocarro->value,
                'color' => 'Rojo',
                'year' => 2022,
            ])
            ->assertCreated();
    }

    public function test_no_se_activa_dos_veces(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson('/api/v1/me/driver-profile', ['license_number' => 'XYZ-99999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['driver_profile']);
    }

    public function test_rechaza_una_licencia_invalida_o_ya_registrada(): void
    {
        DriverProfile::factory()->create(['license_number' => 'ABC-12345']);
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withToken($token)
            ->postJson('/api/v1/me/driver-profile', ['license_number' => 'ABC-12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_number']);

        $this->withToken($token)
            ->postJson('/api/v1/me/driver-profile', ['license_number' => '!!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_number']);

        $this->withToken($token)
            ->postJson('/api/v1/me/driver-profile', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_number']);
    }

    public function test_un_admin_no_puede_activar_el_modo_conductor(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson('/api/v1/me/driver-profile', ['license_number' => 'ABC-12345'])
            ->assertForbidden();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->postJson('/api/v1/me/driver-profile', ['license_number' => 'ABC-12345'])
            ->assertUnauthorized();
    }

    public function test_un_conductor_puede_pedir_un_viaje_como_pasajero(): void
    {
        $conductor = $this->conductor();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson('/api/v1/rides', $this->viajeValido())
            ->assertCreated();

        $this->assertSame($conductor->id, Ride::sole()->passenger_id);
    }

    public function test_un_conductor_puede_pedir_un_mandado(): void
    {
        $conductor = $this->conductor();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson('/api/v1/errands', [
                'description' => 'Comprar pan',
                'origin' => ['latitude' => 3.8653, 'longitude' => -67.9239],
                'destination_site_id' => $this->sitio()->id,
            ])
            ->assertCreated();
    }

    public function test_nadie_acepta_su_propio_viaje(): void
    {
        $cuenta = $this->conductor();
        $viaje = Ride::factory()->create(['passenger_id' => $cuenta->id]);

        $this->withToken(JWTAuth::fromUser($cuenta))
            ->postJson("/api/v1/rides/{$viaje->id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ride']);

        $this->assertNull($viaje->refresh()->driver_id);
    }

    public function test_nadie_acepta_su_propio_mandado(): void
    {
        $cuenta = $this->conductor();
        $mandado = Errand::factory()->create(['passenger_id' => $cuenta->id]);

        $this->withToken(JWTAuth::fromUser($cuenta))
            ->postJson("/api/v1/errands/{$mandado->id}/accept", ['agreed_price' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['errand']);

        $this->assertSame(ErrandStatus::Requested, $mandado->refresh()->status);
    }

    public function test_no_pide_un_viaje_con_uno_activo_como_conductor(): void
    {
        $cuenta = $this->conductor();
        Ride::factory()->create(['status' => RideStatus::Accepted, 'driver_id' => $cuenta->id]);

        $this->withToken(JWTAuth::fromUser($cuenta))
            ->postJson('/api/v1/rides', $this->viajeValido())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ride']);
    }

    public function test_no_acepta_un_viaje_con_uno_activo_como_pasajero(): void
    {
        $cuenta = $this->conductor();
        Ride::factory()->create(['status' => RideStatus::Requested, 'passenger_id' => $cuenta->id]);
        $ajeno = Ride::factory()->create();

        $this->withToken(JWTAuth::fromUser($cuenta))
            ->postJson("/api/v1/rides/{$ajeno->id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ride']);
    }

    public function test_el_aviso_de_viaje_nuevo_no_le_llega_a_quien_lo_pidio(): void
    {
        $grabador = $this->grabarBroadcasts();
        $cuenta = $this->conductor();
        DriverProfile::query()->where('user_id', $cuenta->id)->update([
            'is_available' => true,
            'latitude' => 4.710989,
            'longitude' => -74.072092,
            'location_updated_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($cuenta))
            ->postJson('/api/v1/rides', $this->viajeValido())
            ->assertCreated();

        $this->assertSame([], $grabador->porEvento('ride.requested'));
    }

    public function test_el_historial_se_elige_con_as(): void
    {
        $cuenta = $this->conductor();
        $comoPasajero = Ride::factory()->create(['passenger_id' => $cuenta->id, 'status' => RideStatus::Completed]);
        $comoConductor = Ride::factory()->create(['driver_id' => $cuenta->id, 'status' => RideStatus::Completed]);
        $token = JWTAuth::fromUser($cuenta);

        $this->withToken($token)->getJson('/api/v1/me/rides?as=passenger')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $comoPasajero->id);

        $this->withToken($token)->getJson('/api/v1/me/rides?as=driver')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $comoConductor->id);

        // Sin `as`, como antes: el rol con el que se registró (conductor).
        $this->withToken($token)->getJson('/api/v1/me/rides')
            ->assertOk()->assertJsonPath('data.0.id', $comoConductor->id);
    }

    public function test_un_pasajero_no_puede_ver_historial_de_conductor(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson('/api/v1/me/rides?as=driver')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['as']);
    }

    public function test_registrarse_como_conductor_con_un_correo_existente_guia_a_iniciar_sesion(): void
    {
        $pasajero = User::factory()->create();

        $respuesta = $this->postJson('/api/v1/auth/register/driver', [
            'name' => 'Ana',
            'email' => $pasajero->email,
            'phone' => '+573001112233',
            'password' => 'motoya2026',
            'license_number' => 'ABC-12345',
        ])->assertUnprocessable();

        $this->assertStringContainsString('Inicia sesión', (string) $respuesta->json('errors.email.0'));
    }

    private function conductor(): User
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        return $conductor;
    }

    private function sitio(): Site
    {
        $sitio = Site::factory()->create();
        SiteFare::factory()->create([
            'site_id' => $sitio->id,
            'vehicle_type' => VehicleType::Motocarro,
            'day_price' => 5000,
        ]);

        return $sitio;
    }

    /**
     * @return array<string, mixed>
     */
    private function viajeValido(): array
    {
        return [
            'origin' => ['latitude' => 4.710989, 'longitude' => -74.072092],
            'destination_site_id' => $this->sitio()->id,
            'passenger_count' => 1,
        ];
    }

    private function cambiarDeCuenta(): void
    {
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();
    }
}
