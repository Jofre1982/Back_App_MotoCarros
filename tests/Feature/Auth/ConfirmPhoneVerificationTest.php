<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/me/phone/verification/confirm — ver openapi.yaml.
 */
class ConfirmPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/phone/verification/confirm';

    public function test_confirma_el_celular_con_el_codigo_correcto(): void
    {
        $usuario = User::factory()->create();
        PhoneVerificationCode::factory()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->postJson(self::URI, ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.phone_verified', true);

        $this->assertNotNull($usuario->fresh()->phone_verified_at);
        $this->assertDatabaseCount('phone_verification_codes', 0);
    }

    public function test_rechaza_un_codigo_incorrecto_y_cuenta_el_intento(): void
    {
        $usuario = User::factory()->create();
        $verificacion = PhoneVerificationCode::factory()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->postJson(self::URI, ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertSame(1, $verificacion->fresh()->attempts);
        $this->assertNull($usuario->fresh()->phone_verified_at);
    }

    public function test_rechaza_un_codigo_vencido(): void
    {
        $usuario = User::factory()->create();
        PhoneVerificationCode::factory()->expired()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->postJson(self::URI, ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_rechaza_cuando_se_agotaron_los_intentos(): void
    {
        $usuario = User::factory()->create();
        PhoneVerificationCode::factory()->exhausted()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->postJson(self::URI, ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_rechaza_sin_ninguna_verificacion_pendiente(): void
    {
        $usuario = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($usuario))
            ->postJson(self::URI, ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_rechaza_sin_codigo_en_el_body(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $this->postJson(self::URI, ['code' => '123456'])->assertUnauthorized();
    }
}
