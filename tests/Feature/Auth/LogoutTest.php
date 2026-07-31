<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tymon\JWTAuth\Blacklist;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/auth/logout — ver openapi.yaml.
 *
 * La regla que justifica el endpoint: quien pierde el dispositivo necesita que
 * su token deje de servir *ahora*, no cuando venza. De ahí las dos diferencias
 * con el refresh que estos tests fijan: el token cerrado tampoco puede
 * renovarse, y no hay periodo de gracia.
 */
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private const LOGOUT_URI = '/api/v1/auth/logout';

    private const REFRESH_URI = '/api/v1/auth/refresh';

    private const PROBE_URI = '/api/v1/_probe-protegida';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:api')->get(self::PROBE_URI, function () {
            return response()->json(['user_id' => auth('api')->id()]);
        });
    }

    public function test_cierra_la_sesion_y_responde_sin_cuerpo(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withToken($token)
            ->postJson(self::LOGOUT_URI)
            ->assertNoContent();
    }

    public function test_el_token_cerrado_deja_de_servir_para_la_api(): void
    {
        // El criterio de aceptación de la historia #9, y la razón por la que el
        // cierre de sesión no puede usar la gracia de blacklist del refresh:
        // no se viaja en el tiempo entre las dos requests, así que si el token
        // siguiera aceptándose durante los 30 s de gracia, esto fallaría.
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withToken($token)->postJson(self::LOGOUT_URI)->assertNoContent();

        $this->olvidarGuards();

        $this->withToken($token)
            ->getJson(self::PROBE_URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_el_token_cerrado_tampoco_sirve_para_renovarse(): void
    {
        // Si se pudiera canjear por uno nuevo, cerrar sesión no serviría de
        // nada frente al caso que la historia describe —un teléfono perdido—:
        // quien tuviera el token robado recuperaría el acceso con un refresh.
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withToken($token)->postJson(self::LOGOUT_URI)->assertNoContent();

        $this->olvidarGuards();

        $this->withToken($token)
            ->postJson(self::REFRESH_URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_cerrar_la_sesion_no_toca_la_gracia_configurada_para_el_refresh(): void
    {
        // El cierre de sesión ignora la gracia, pero no puede *cambiarla*: el
        // Blacklist es un singleton compartido con el refresh, así que si el
        // logout lo dejara en 0, la primera renovación posterior tumbaría las
        // requests que el cliente ya tenía en vuelo.
        $graciaConfigurada = $this->app->make(Blacklist::class)->getGracePeriod();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::LOGOUT_URI)
            ->assertNoContent();

        $this->assertSame(
            $graciaConfigurada,
            $this->app->make(Blacklist::class)->getGracePeriod(),
        );

        // Y se comprueba sobre el comportamiento, no solo sobre el getter: el
        // token renovado sigue sirviendo durante la gracia de 30 s.
        $otroToken = JWTAuth::fromUser(User::factory()->create());

        $this->olvidarGuards();
        $this->withToken($otroToken)->postJson(self::REFRESH_URI)->assertOk();

        $this->travel(20)->seconds();

        $this->olvidarGuards();
        $this->withToken($otroToken)->postJson(self::REFRESH_URI)->assertOk();
    }

    public function test_no_cierra_las_demas_sesiones_de_la_misma_cuenta(): void
    {
        // Cerrar sesión en el teléfono no puede echar a la tableta: se invalida
        // el token enviado, no la cuenta. El cierre remoto en todos los
        // dispositivos queda fuera de la historia #9 y necesita llevar registro
        // de los tokens activos por usuario.
        $usuario = User::factory()->create();

        $telefono = JWTAuth::fromUser($usuario);
        $tableta = JWTAuth::fromUser($usuario);

        $this->withToken($telefono)->postJson(self::LOGOUT_URI)->assertNoContent();

        $this->olvidarGuards();

        $this->withToken($tableta)
            ->getJson(self::PROBE_URI)
            ->assertOk()
            ->assertJson(['user_id' => $usuario->id]);
    }

    public function test_rechaza_el_cierre_de_sesion_sin_token(): void
    {
        $this->postJson(self::LOGOUT_URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_el_cierre_de_sesion_con_un_token_ilegible(): void
    {
        $this->withToken('no-es-un-jwt')
            ->postJson(self::LOGOUT_URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_el_cierre_de_sesion_con_un_token_expirado(): void
    {
        // El segundo criterio de aceptación: un token vencido responde 401, no
        // un error sin controlar. A diferencia del refresh, acá el token
        // expirado no tiene nada que hacer — ya no sirve para consumir la API,
        // que es exactamente el estado al que el logout quiere llevarlo.
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->travel(30)->minutes();

        $this->withToken($token)
            ->postJson(self::LOGOUT_URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_cerrar_la_sesion_dos_veces_responde_401(): void
    {
        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withToken($token)->postJson(self::LOGOUT_URI)->assertNoContent();

        $this->olvidarGuards();

        $this->withToken($token)
            ->postJson(self::LOGOUT_URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    /**
     * En producción cada request estrena aplicación; acá los requests comparten
     * instancia, así que el guard `api` conserva cacheado el usuario que
     * resolvió en la request anterior. Sin esto, la siguiente pasaría sin
     * volver a validar el token contra la blacklist.
     */
    private function olvidarGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
