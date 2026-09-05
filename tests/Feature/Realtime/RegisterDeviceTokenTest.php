<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/me/device-token — ver openapi.yaml.
 */
class RegisterDeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/device-token';

    public function test_registra_el_token_y_lo_asocia_a_la_cuenta(): void
    {
        $conductor = User::factory()->driver()->create();

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, ['token' => 'fcm-abc123', 'platform' => 'android'])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $respuesta->assertJsonMissingPath('data.token');
        $this->assertNotNull($respuesta->json('data.registered_at'));

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $conductor->id,
            'token' => 'fcm-abc123',
            'platform' => 'android',
        ]);
    }

    public function test_un_pasajero_tambien_puede_registrar_su_token(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson(self::URI, ['token' => 'fcm-pasajero-1', 'platform' => 'ios'])
            ->assertCreated();

        $this->assertDatabaseHas('device_tokens', ['user_id' => $pasajero->id, 'platform' => 'ios']);
    }

    public function test_volver_a_registrar_el_mismo_token_lo_actualiza_en_vez_de_duplicarlo(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, ['token' => 'fcm-mismo', 'platform' => 'android'])
            ->assertCreated();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, ['token' => 'fcm-mismo', 'platform' => 'ios'])
            ->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['token' => 'fcm-mismo', 'platform' => 'ios']);
    }

    public function test_el_mismo_dispositivo_puede_mudarse_a_otra_cuenta(): void
    {
        // El token ya registrado por otra cuenta se siembra directamente en
        // la base (no con una segunda request autenticada): dos requests
        // como cuentas distintas en un mismo test chocan con el caché interno
        // del parser de JWT sobre la request ya resuelta, algo que no ocurre
        // en producción —cada request real arranca su propio parser— pero sí
        // dentro de un mismo método de test. Mismo criterio que el resto de
        // la suite (ver `RegisterVehicleTest`), que nunca autentica dos
        // cuentas distintas dentro de un mismo test.
        $primeraCuenta = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $primeraCuenta->id, 'token' => 'fcm-compartido']);

        $segundaCuenta = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($segundaCuenta))
            ->postJson(self::URI, ['token' => 'fcm-compartido', 'platform' => 'android'])
            ->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['token' => 'fcm-compartido', 'user_id' => $segundaCuenta->id]);
    }

    public function test_rechaza_una_plataforma_invalida(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, ['token' => 'fcm-x', 'platform' => 'windows'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_rechaza_sin_token(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, ['platform' => 'android'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');
    }

    public function test_rechaza_la_solicitud_sin_autenticar(): void
    {
        $this->postJson(self::URI, ['token' => 'fcm-x', 'platform' => 'android'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('device_tokens', 0);
    }
}
