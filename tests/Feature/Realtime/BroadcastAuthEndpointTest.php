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
 * nulo autoriza cualquier cosa sin preguntar. Acá se fuerza `reverb` con
 * credenciales de prueba: la firma es un HMAC local, así que no hace falta
 * ningún servidor corriendo.
 *
 * La conexión se fija en el entorno *antes* de que arranque la aplicación, no
 * con `Config::set` después: los canales de routes/channels.php se registran
 * contra el broadcaster que esté configurado al bootear, así que cambiar la
 * conexión más tarde deja al nuevo broadcaster sin ningún canal declarado.
 */
class BroadcastAuthEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/broadcasting/auth';

    private const SOCKET_ID = '123456.789012';

    private const APP_KEY = 'motoya-testing-key';

    /** @var array<string, string> */
    private const ENTORNO_REVERB = [
        'BROADCAST_CONNECTION' => 'reverb',
        'REVERB_APP_ID' => 'motoya-testing',
        'REVERB_APP_KEY' => self::APP_KEY,
        'REVERB_APP_SECRET' => 'motoya-testing-secret',
        'REVERB_HOST' => '127.0.0.1',
        'REVERB_PORT' => '8080',
        'REVERB_SCHEME' => 'http',
    ];

    /** @var array<string, string|false> */
    private array $entornoOriginal = [];

    protected function setUp(): void
    {
        foreach (self::ENTORNO_REVERB as $clave => $valor) {
            $this->entornoOriginal[$clave] = $_SERVER[$clave] ?? false;
            $_SERVER[$clave] = $_ENV[$clave] = $valor;
            putenv("{$clave}={$valor}");
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->entornoOriginal as $clave => $valor) {
            if ($valor === false) {
                unset($_SERVER[$clave], $_ENV[$clave]);
                putenv($clave);

                continue;
            }

            $_SERVER[$clave] = $_ENV[$clave] = $valor;
            putenv("{$clave}={$valor}");
        }
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

        $response = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [
                'socket_id' => self::SOCKET_ID,
                'channel_name' => "private-driver.{$conductor->getKey()}",
            ]);

        $response->assertOk()->assertJsonStructure(['auth']);

        $this->assertStringStartsWith(self::APP_KEY.':', $response->json('auth'));
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
