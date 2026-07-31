<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\LogoutAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Blacklist;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\JWT;

/**
 * La Action invocada directo, sin pasar por HTTP: es lo que garantiza que el
 * caso de uso sirva igual desde un comando o un job (ver .claude/STANDARDS.md).
 *
 * Lo que se fija acá es que la invalidación sea inmediata y que el efecto sobre
 * el Blacklist compartido termine donde termina la Action.
 */
class LogoutActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalida_el_token_de_inmediato(): void
    {
        // Sin avanzar el reloj: con la gracia configurada (30 s) el token
        // seguiría considerándose válido, que es justo lo que el cierre de
        // sesión no puede permitir.
        $token = JWTAuth::fromUser(User::factory()->create());

        // Se lee antes de cerrar la sesión: después, `getPayload()` valida
        // contra la blacklist y saldría por excepción en vez de devolverlo.
        $payload = JWTAuth::setToken($token)->getPayload();

        $this->action()->handle($token);

        $this->assertTrue($this->app->make(Blacklist::class)->has($payload));
    }

    public function test_el_token_invalidado_ya_no_autentica(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->action()->handle($token);

        $this->expectException(TokenBlacklistedException::class);

        JWTAuth::setToken($token)->authenticate();
    }

    public function test_deja_la_gracia_configurada_como_estaba(): void
    {
        // El Blacklist es un singleton compartido con el refresh: bajarle la
        // gracia a 0 y no devolverla dejaría a las renovaciones sin el margen
        // que las requests en vuelo necesitan.
        $blacklist = $this->app->make(Blacklist::class);
        $graciaConfigurada = $blacklist->getGracePeriod();

        $this->action()->handle(JWTAuth::fromUser(User::factory()->create()));

        $this->assertSame($graciaConfigurada, $blacklist->getGracePeriod());
    }

    public function test_devuelve_la_gracia_aunque_la_invalidacion_falle(): void
    {
        $blacklist = $this->app->make(Blacklist::class);
        $graciaConfigurada = $blacklist->getGracePeriod();

        try {
            $this->action()->handle('no-es-un-jwt');
        } catch (TokenInvalidException) {
            // El fallo es el esperado; lo que se comprueba es lo de abajo.
        }

        $this->assertSame($graciaConfigurada, $blacklist->getGracePeriod());
    }

    public function test_rechaza_un_token_ilegible(): void
    {
        $this->expectException(TokenInvalidException::class);

        $this->action()->handle('no-es-un-jwt');
    }

    public function test_rechaza_un_token_expirado(): void
    {
        // Que el motivo sea una excepción de jwt-auth —y no un `void`
        // silencioso— es lo que sostiene el 401 del endpoint: la traducción a
        // HTTP vive en bootstrap/app.php, no en el controller.
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->travel(30)->minutes();

        $this->expectException(TokenExpiredException::class);

        $this->action()->handle($token);
    }

    public function test_no_invalida_los_demas_tokens_de_la_misma_cuenta(): void
    {
        $usuario = User::factory()->create();

        $telefono = JWTAuth::fromUser($usuario);
        $tableta = JWTAuth::fromUser($usuario);

        $this->action()->handle($telefono);

        $this->assertSame(
            $usuario->id,
            JWTAuth::setToken($tableta)->authenticate()?->id,
        );
    }

    public function test_no_cierra_nada_si_la_blacklist_esta_deshabilitada(): void
    {
        // Escribir en el `Blacklist` directo —en vez de pasar por
        // `Manager::invalidate()`— se salta el único punto donde jwt-auth
        // comprueba que la blacklist esté habilitada. Sin esta verificación, la
        // Action escribía una entrada que después nadie consulta al validar y
        // terminaba sin excepción: el token seguía sirviendo hasta vencer solo
        // mientras el endpoint respondía 204.
        config(['jwt.blacklist_enabled' => false]);

        $token = JWTAuth::fromUser(User::factory()->create());
        $payload = JWTAuth::setToken($token)->getPayload();

        try {
            $this->action()->handle($token);
            $this->fail('Se esperaba una JWTException por la blacklist deshabilitada.');
        } catch (JWTException $e) {
            // Tiene que ser la clase base y no una de sus hijas: las hijas
            // (TokenInvalid/TokenExpired) están mapeadas a 401 en
            // bootstrap/app.php, y esto no es culpa del token del cliente sino
            // del despliegue. Solo la clase base escala a 500.
            $this->assertSame(JWTException::class, $e::class);
        }

        $this->assertFalse($this->app->make(Blacklist::class)->has($payload));
    }

    public function test_no_deja_el_token_muerto_cacheado_en_la_instancia_del_guard(): void
    {
        // `JWT::getToken()` devuelve lo que tenga cacheado antes de leer la
        // cabecera, y el guard `api` se construye con el singleton `tymon.jwt`
        // —no con `tymon.jwt.auth`, que es otro—. Si la Action invalidara el
        // token sobre la instancia equivocada, o no lo soltara, la siguiente
        // autenticación en un contenedor que sobrevive a la request reusaría
        // este token ya invalidado y rechazaría credenciales válidas.
        $this->action()->handle(JWTAuth::fromUser(User::factory()->create()));

        $this->assertNull($this->app->make(JWT::class)->getToken());
    }

    private function action(): LogoutAction
    {
        return $this->app->make(LogoutAction::class);
    }
}
