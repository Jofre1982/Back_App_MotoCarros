<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\LoginAction;
use App\DTOs\LoginCredentials;
use App\Enums\UserRole;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * La Action invocada directo, sin pasar por HTTP: es lo que garantiza que el
 * caso de uso sirva igual desde un comando o un job (ver .claude/STANDARDS.md).
 *
 * Que el fallo sea una excepción del dominio, y la misma para ambos motivos, es
 * lo que hace que la indistinguibilidad de los dos casos no dependa de que el
 * controller se acuerde de unificarlos.
 */
class LoginActionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'motoya2026';

    public function test_devuelve_la_cuenta_para_credenciales_validas(): void
    {
        $usuario = $this->cuentaRegistrada();

        $resultado = $this->action()->handle($this->credenciales());

        $this->assertSame($usuario->id, $resultado->user->id);
        $this->assertSame('ana@example.com', $resultado->user->email);
        $this->assertSame(UserRole::Passenger, $resultado->user->role);
    }

    public function test_el_token_identifica_a_la_cuenta_que_inicio_sesion(): void
    {
        $usuario = $this->cuentaRegistrada();

        $resultado = $this->action()->handle($this->credenciales());

        $identificado = JWTAuth::setToken($resultado->token->accessToken)->authenticate();

        $this->assertInstanceOf(User::class, $identificado);
        $this->assertSame($usuario->id, $identificado->id);
    }

    public function test_el_token_declara_su_vencimiento_segun_el_ttl_configurado(): void
    {
        $this->freezeSecond();

        $this->cuentaRegistrada();

        $resultado = $this->action()->handle($this->credenciales());

        $this->assertSame('bearer', $resultado->token->tokenType);
        // 15 minutos de TTL (`jwt.ttl`) expresados en segundos.
        $this->assertSame(900, $resultado->token->expiresInSeconds);
    }

    public function test_autentica_igual_a_un_conductor(): void
    {
        // El caso de uso es "iniciar sesión", no "iniciar sesión como pasajero":
        // el rol no participa de la decisión.
        $conductor = $this->cuentaRegistrada(conductor: true);

        $resultado = $this->action()->handle($this->credenciales());

        $this->assertSame($conductor->id, $resultado->user->id);
        $this->assertTrue($resultado->user->isDriver());
    }

    public function test_rechaza_una_contrasena_incorrecta(): void
    {
        $this->cuentaRegistrada();

        $this->expectException(InvalidCredentialsException::class);

        $this->action()->handle(new LoginCredentials(
            email: 'ana@example.com',
            password: 'motoya2027',
        ));
    }

    public function test_rechaza_un_email_que_no_corresponde_a_ninguna_cuenta(): void
    {
        // Mismo tipo de excepción que la contraseña incorrecta: la Action no
        // ofrece forma de distinguir los dos motivos, así que ninguna capa de
        // arriba puede filtrarla por accidente.
        $this->expectException(InvalidCredentialsException::class);

        $this->action()->handle(new LoginCredentials(
            email: 'nadie@example.com',
            password: self::PASSWORD,
        ));
    }

    public function test_una_contrasena_que_no_se_puede_hashear_falla_como_credencial_invalida(): void
    {
        // El hash de descarte del email inexistente es la única línea de la
        // Action que puede lanzar algo que no sea del dominio: `Hash::make()`
        // con un byte nulo sale por `RuntimeException`. Que acá siga siendo una
        // `InvalidCredentialsException` es lo que sostiene el `@throws` del
        // método para quien llame al caso de uso sin pasar por HTTP —un comando
        // o un job—, donde no hay Form Request que filtre la entrada.
        $this->expectException(InvalidCredentialsException::class);

        $this->action()->handle(new LoginCredentials(
            email: 'nadie@example.com',
            password: "a\x00b",
        ));
    }

    public function test_no_distingue_el_motivo_del_fallo_con_una_contrasena_que_no_se_puede_hashear(): void
    {
        // Y el motivo tampoco se filtra por esta rama: el mensaje es el mismo
        // que el de la contraseña incorrecta contra una cuenta que sí existe.
        $this->cuentaRegistrada();

        $porContrasena = $this->motivoDelFallo('ana@example.com', "a\x00b");
        $porEmail = $this->motivoDelFallo('nadie@example.com', "a\x00b");

        $this->assertSame($porContrasena, $porEmail);
    }

    public function test_no_distingue_el_motivo_del_fallo_en_el_mensaje(): void
    {
        // Si la excepción llevara el motivo, tarde o temprano alguien lo
        // renderizaría. El mensaje es el mismo para ambos casos, y el detalle
        // que sirve para diagnosticar vive en los logs de la aplicación, no en
        // el objeto que viaja hacia el controller.
        $this->cuentaRegistrada();

        $porContrasena = $this->motivoDelFallo('ana@example.com', 'motoya2027');
        $porEmail = $this->motivoDelFallo('nadie@example.com', self::PASSWORD);

        $this->assertSame($porContrasena, $porEmail);
    }

    private function motivoDelFallo(string $email, string $password): string
    {
        try {
            $this->action()->handle(new LoginCredentials($email, $password));
        } catch (InvalidCredentialsException $e) {
            return $e->getMessage();
        }

        $this->fail('Se esperaba una InvalidCredentialsException.');
    }

    private function action(): LoginAction
    {
        return $this->app->make(LoginAction::class);
    }

    private function cuentaRegistrada(bool $conductor = false): User
    {
        $factory = $conductor ? User::factory()->driver() : User::factory();

        return $factory->create([
            'email' => 'ana@example.com',
            'password' => self::PASSWORD,
        ]);
    }

    private function credenciales(): LoginCredentials
    {
        return new LoginCredentials(
            email: 'ana@example.com',
            password: self::PASSWORD,
        );
    }
}
