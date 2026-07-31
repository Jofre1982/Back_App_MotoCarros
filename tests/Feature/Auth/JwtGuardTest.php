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
 * Verifica que el guard `api` (driver jwt) protege realmente las rutas.
 *
 * La ruta protegida se registra aquí y no en routes/api.php a propósito: este
 * test cubre el middleware, no un endpoint de negocio. Los endpoints reales
 * llegan con sus propias historias (login, perfil, viajes) y cada uno debe
 * estar documentado en openapi.yaml — una ruta de prueba permanente en
 * routes/api.php rompería esa correspondencia (ver static_conformance.py).
 */
class JwtGuardTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE_URI = '/api/v1/_probe-protegida';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:api')->get(self::PROBE_URI, function () {
            return response()->json(['user_id' => auth('api')->id()]);
        });
    }

    public function test_ruta_protegida_rechaza_request_sin_token(): void
    {
        $this->getJson(self::PROBE_URI)->assertUnauthorized();
    }

    public function test_ruta_protegida_rechaza_un_token_con_firma_invalida(): void
    {
        $this->withToken('no-es-un-jwt')
            ->getJson(self::PROBE_URI)
            ->assertUnauthorized();
    }

    public function test_ruta_protegida_acepta_un_token_valido_y_resuelve_al_usuario(): void
    {
        $user = $this->crearPasajero();
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->getJson(self::PROBE_URI)
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    }

    public function test_ruta_protegida_rechaza_un_token_expirado(): void
    {
        $token = JWTAuth::fromUser($this->crearPasajero());

        // El access token dura 15 minutos (config/jwt.php).
        $this->travel(16)->minutes();

        $this->withToken($token)
            ->getJson(self::PROBE_URI)
            ->assertUnauthorized();
    }

    public function test_el_token_lleva_el_rol_como_claim_para_la_ui_del_cliente(): void
    {
        $conductor = $this->crearConductor();

        $payload = JWTAuth::setToken(JWTAuth::fromUser($conductor))->getPayload();

        $this->assertSame($conductor->id, (int) $payload->get('sub'));
        $this->assertSame(UserRole::Driver->value, $payload->get('role'));
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

    private function crearConductor(): User
    {
        return User::create([
            'name' => 'Carlos López',
            'email' => 'carlos@example.com',
            'phone' => '+573009876543',
            'password' => 'secret',
            'role' => UserRole::Driver,
        ]);
    }
}
