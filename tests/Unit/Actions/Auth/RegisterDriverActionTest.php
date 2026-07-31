<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\RegisterDriverAction;
use App\DTOs\DriverRegistration;
use App\Enums\UserRole;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * La Action invocada directo, sin pasar por HTTP: es lo que garantiza que el
 * caso de uso sirva igual desde un comando o un job (ver .claude/STANDARDS.md).
 */
class RegisterDriverActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_la_cuenta_con_rol_conductor(): void
    {
        $resultado = $this->action()->handle($this->registracion());

        $this->assertSame('Carlos Ramírez', $resultado->user->name);
        $this->assertSame('carlos@example.com', $resultado->user->email);
        $this->assertSame('+573009876543', $resultado->user->phone);
        $this->assertSame(UserRole::Driver, $resultado->user->role);
        $this->assertTrue($resultado->user->exists);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_crea_el_perfil_de_conductor_asociado_a_la_cuenta(): void
    {
        $resultado = $this->action()->handle($this->registracion());

        $this->assertDatabaseCount('driver_profiles', 1);
        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $resultado->user->id,
            'license_number' => 'LIC-445566',
        ]);
    }

    public function test_no_deja_la_cuenta_creada_si_falla_el_perfil(): void
    {
        // Cuenta y perfil se crean juntos o no se crea ninguno: una cuenta de
        // conductor sin licencia no es un estado válido del dominio, y el
        // índice único de `license_number` puede rechazar la escritura aunque
        // la validación del Form Request haya pasado (dos altas concurrentes
        // con la misma licencia).
        DriverProfile::factory()->create(['license_number' => 'LIC-445566']);

        $usuariosPrevios = User::count();

        try {
            $this->action()->handle($this->registracion());
            $this->fail('Se esperaba que la licencia duplicada abortara el alta.');
        } catch (QueryException) {
            // El fallo en sí es lo esperado; lo que se comprueba es que no dejó
            // rastro.
        }

        $this->assertSame($usuariosPrevios, User::count());
        $this->assertNull(User::firstWhere('email', 'carlos@example.com'));
    }

    public function test_guarda_la_contrasena_hasheada(): void
    {
        $resultado = $this->action()->handle($this->registracion());

        $this->assertNotSame('motoya2026', $resultado->user->password);
        $this->assertTrue(Hash::check('motoya2026', $resultado->user->password));
    }

    public function test_devuelve_un_token_que_identifica_al_conductor_creado(): void
    {
        $resultado = $this->action()->handle($this->registracion());

        $identificado = JWTAuth::setToken($resultado->token->accessToken)->authenticate();

        $this->assertInstanceOf(User::class, $identificado);
        $this->assertSame($resultado->user->id, $identificado->id);
    }

    public function test_el_token_declara_su_vencimiento_segun_el_ttl_configurado(): void
    {
        $this->freezeSecond();

        $resultado = $this->action()->handle($this->registracion());

        $this->assertSame('bearer', $resultado->token->tokenType);
        // 15 minutos de TTL (`jwt.ttl`) expresados en segundos.
        $this->assertSame(900, $resultado->token->expiresInSeconds);
    }

    public function test_el_rol_no_depende_de_la_entrada(): void
    {
        // `DriverRegistration` no tiene campo de rol a propósito: el caso de
        // uso es registrar conductores, así que no hay forma de pedir otro.
        $resultado = $this->action()->handle($this->registracion());

        $this->assertTrue($resultado->user->isDriver());
        $this->assertFalse($resultado->user->isPassenger());
    }

    private function action(): RegisterDriverAction
    {
        return $this->app->make(RegisterDriverAction::class);
    }

    private function registracion(): DriverRegistration
    {
        return new DriverRegistration(
            name: 'Carlos Ramírez',
            email: 'carlos@example.com',
            phone: '+573009876543',
            password: 'motoya2026',
            licenseNumber: 'LIC-445566',
        );
    }
}
