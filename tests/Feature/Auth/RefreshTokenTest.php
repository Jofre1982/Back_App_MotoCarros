<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/auth/refresh — ver openapi.yaml.
 *
 * La regla que justifica el endpoint: un access token expirado ya no sirve
 * para consumir la API, pero sí para pedir uno nuevo mientras siga dentro de
 * la ventana de refresh (14 días desde su emisión).
 */
class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private const REFRESH_URI = '/api/v1/auth/refresh';

    private const PROBE_URI = '/api/v1/_probe-protegida';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:api')->get(self::PROBE_URI, function () {
            return response()->json(['user_id' => auth('api')->id()]);
        });
    }

    public function test_devuelve_un_token_nuevo_con_la_forma_del_contrato(): void
    {
        $token = JWTAuth::fromUser($this->crearPasajero());

        $response = $this->withToken($token)->postJson(self::REFRESH_URI);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']])
            ->assertJsonPath('data.token_type', 'bearer')
            // 15 minutos de TTL expresados en segundos.
            ->assertJsonPath('data.expires_in', 900);

        $this->assertIsString($response->json('data.access_token'));
        $this->assertNotSame($token, $response->json('data.access_token'));
    }

    public function test_un_token_expirado_no_sirve_para_la_api_pero_si_para_renovar(): void
    {
        $user = $this->crearPasajero();
        $token = JWTAuth::fromUser($user);

        // Más allá del TTL (15 min) pero muy dentro de la ventana de refresh.
        $this->travel(30)->minutes();

        // Expirado: la API ya no lo acepta.
        $this->withToken($token)->getJson(self::PROBE_URI)->assertUnauthorized();

        // Pero todavía se puede canjear por uno nuevo.
        $nuevoToken = $this->withToken($token)
            ->postJson(self::REFRESH_URI)
            ->assertOk()
            ->json('data.access_token');

        // Y el token nuevo sí sirve, para el mismo usuario.
        $this->withToken($nuevoToken)
            ->getJson(self::PROBE_URI)
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    }

    public function test_no_se_puede_renovar_pasada_la_ventana_de_refresh(): void
    {
        $token = JWTAuth::fromUser($this->crearPasajero());

        // La ventana es de 14 días desde la emisión del token original.
        $this->travel(15)->days();

        $this->withToken($token)
            ->postJson(self::REFRESH_URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_renovacion_sin_token(): void
    {
        $this->postJson(self::REFRESH_URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_renovacion_con_un_token_ilegible(): void
    {
        $this->withToken('no-es-un-jwt')
            ->postJson(self::REFRESH_URI)
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    private function crearPasajero(): User
    {
        return User::create([
            'name' => 'Ana García',
            'email' => 'ana@example.com',
            'phone' => '+573001234567',
            'password' => 'secret',
            'role' => UserRole::Passenger,
        ]);
    }
}
