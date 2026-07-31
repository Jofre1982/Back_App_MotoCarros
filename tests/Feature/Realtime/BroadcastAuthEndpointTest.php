<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * POST /api/v1/broadcasting/auth — la puerta de entrada a los canales privados
 * (issue #5).
 *
 * Los tests de los canales prueban la regla; este prueba que esa regla se
 * aplique de verdad cuando el cliente se suscribe: con el JWT del usuario, por
 * el guard `api`, y devolviendo la firma que el cliente le reenvía a Reverb.
 *
 * La suite corre con BROADCAST_CONNECTION=null (phpunit.xml), y el broadcaster
 * nulo autoriza cualquier cosa sin preguntar. Acá se fuerza `reverb`, que firma
 * con las credenciales `REVERB_APP_*` de phpunit.xml: la firma es un HMAC
 * local, así que no hace falta ningún servidor corriendo.
 *
 * La conexión se fija en el entorno *antes* de que arranque la aplicación, no
 * con `Config::set` después: los canales de routes/channels.php se registran
 * contra el broadcaster que esté configurado al bootear, así que cambiar la
 * conexión más tarde deja al nuevo broadcaster sin ningún canal declarado.
 *
 * Que funcione fijándola desde acá es un caso particular, no el mecanismo
 * general: `BROADCAST_CONNECTION` está declarada en phpunit.xml, así que
 * phpdotenv nunca la considera cargada desde `.env` y nunca la repisa. Una
 * variable que *no* esté en phpunit.xml no se puede fijar así — el writer
 * inmutable de phpdotenv la sobrescribe con el valor de `.env` en cada boot
 * posterior al primero. Por eso las credenciales viven en phpunit.xml.
 */
class BroadcastAuthEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/broadcasting/auth';

    private const SOCKET_ID = '123456.789012';

    /**
     * Espejo de `REVERB_APP_KEY` en phpunit.xml: Reverb antepone la clave de la
     * aplicación a la firma que devuelve.
     */
    private const APP_KEY = 'motoya-testing-key';

    /** Espejo de `REVERB_APP_SECRET` en phpunit.xml: con él se firma el HMAC. */
    private const APP_SECRET = 'motoya-testing-secret';

    private const CONEXION = 'BROADCAST_CONNECTION';

    private string|false $conexionOriginal = false;

    protected function setUp(): void
    {
        $original = $_SERVER[self::CONEXION] ?? false;
        $this->conexionOriginal = is_string($original) ? $original : false;

        $_SERVER[self::CONEXION] = $_ENV[self::CONEXION] = 'reverb';
        putenv(self::CONEXION.'=reverb');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->conexionOriginal === false) {
            unset($_SERVER[self::CONEXION], $_ENV[self::CONEXION]);
            putenv(self::CONEXION);

            return;
        }

        $_SERVER[self::CONEXION] = $_ENV[self::CONEXION] = $this->conexionOriginal;
        putenv(self::CONEXION.'='.$this->conexionOriginal);
    }

    public function test_sin_token_no_se_autoriza_ningun_canal(): void
    {
        $conductor = $this->conductorOperativo();

        $this->postJson(self::URI, [
            'socket_id' => self::SOCKET_ID,
            'channel_name' => "private-driver.{$conductor->getKey()}",
        ])->assertUnauthorized();
    }

    public function test_un_conductor_recibe_la_firma_de_su_propio_canal(): void
    {
        $conductor = $this->conductorOperativo();
        $canal = "private-driver.{$conductor->getKey()}";

        $response = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [
                'socket_id' => self::SOCKET_ID,
                'channel_name' => $canal,
            ]);

        $response->assertOk()->assertJsonStructure(['auth']);

        // Se compara la firma completa, no solo el prefijo: con el prefijo
        // bastaba que `REVERB_APP_KEY` fuera la esperada, y una credencial de
        // firma vacía o distinta pasaba igual. El HMAC obliga a que las dos
        // credenciales sean las que este test dice estar ejercitando.
        $esperada = self::APP_KEY.':'.hash_hmac(
            'sha256',
            self::SOCKET_ID.':'.$canal,
            self::APP_SECRET
        );

        $this->assertSame($esperada, $response->json('auth'));
    }

    public function test_un_conductor_no_recibe_la_firma_del_canal_de_otro(): void
    {
        $conductor = $this->conductorOperativo();
        $otro = $this->conductorOperativo();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [
                'socket_id' => self::SOCKET_ID,
                'channel_name' => "private-driver.{$otro->getKey()}",
            ])
            ->assertForbidden();
    }

    /**
     * Mientras no exista el modelo `Ride` (historia #15) nadie participa de
     * ningún viaje, así que el canal deniega — que es lo que tiene que pasar
     * antes que abrirlo "por ahora".
     */
    public function test_el_canal_de_viaje_deniega_mientras_no_existan_viajes(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, [
                'socket_id' => self::SOCKET_ID,
                'channel_name' => 'private-ride.1',
            ])
            ->assertForbidden();
    }

    public function test_un_canal_que_no_esta_declarado_se_rechaza(): void
    {
        $conductor = $this->conductorOperativo();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [
                'socket_id' => self::SOCKET_ID,
                'channel_name' => 'private-admin.1',
            ])
            ->assertForbidden();
    }

    private function conductorOperativo(): User
    {
        $conductor = User::factory()->driver()->create();

        DriverProfile::factory()->for($conductor)->create();

        return $conductor;
    }
}
