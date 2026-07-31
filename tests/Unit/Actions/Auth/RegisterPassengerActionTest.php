<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\RegisterPassengerAction;
use App\DTOs\PassengerRegistration;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * La Action invocada directo, sin pasar por HTTP: es lo que garantiza que el
 * caso de uso sirva igual desde un comando o un job (ver .claude/STANDARDS.md).
 *
 * Necesita base de datos porque el caso de uso *es* crear la cuenta; lo que no
 * toca es el ciclo request/response.
 */
class RegisterPassengerActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_la_cuenta_con_rol_pasajero(): void
    {
        $resultado = $this->action()->handle($this->registracion());

        $this->assertSame('Ana García', $resultado->user->name);
        $this->assertSame('ana@example.com', $resultado->user->email);
        $this->assertSame('+573001234567', $resultado->user->phone);
        $this->assertSame(UserRole::Passenger, $resultado->user->role);
        $this->assertTrue($resultado->user->exists);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_guarda_la_contrasena_hasheada(): void
    {
        $resultado = $this->action()->handle($this->registracion());

        $this->assertNotSame('motoya2026', $resultado->user->password);
        $this->assertTrue(Hash::check('motoya2026', $resultado->user->password));
    }

    public function test_devuelve_un_token_que_identifica_al_pasajero_creado(): void
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
        // `PassengerRegistration` no tiene campo de rol a propósito: el caso de
        // uso es registrar pasajeros, así que no hay forma de pedir otro.
        $resultado = $this->action()->handle($this->registracion());

        $this->assertTrue($resultado->user->isPassenger());
        $this->assertFalse($resultado->user->isDriver());
    }

    private function action(): RegisterPassengerAction
    {
        return $this->app->make(RegisterPassengerAction::class);
    }

    private function registracion(): PassengerRegistration
    {
        return new PassengerRegistration(
            name: 'Ana García',
            email: 'ana@example.com',
            phone: '+573001234567',
            password: 'motoya2026',
        );
    }
}
