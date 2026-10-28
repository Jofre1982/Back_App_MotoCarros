<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Codes\AccountCodeSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeAccountCodeSender;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/me/phone/verification — ver openapi.yaml.
 */
class RequestPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/phone/verification';

    private function gatewayFalso(): AccountCodeSender
    {
        return new FakeAccountCodeSender;
    }

    public function test_genera_un_codigo_y_lo_envia_al_correo(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);

        $gateway = new FakeAccountCodeSender;
        $this->app->instance(AccountCodeSender::class, $gateway);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->postJson(self::URI)
            ->assertNoContent();

        $this->assertDatabaseHas('phone_verification_codes', ['user_id' => $usuario->id]);
        $this->assertSame($usuario->email, $gateway->lastEmail());
        $this->assertCount(1, $gateway->sent);
    }

    public function test_pedir_un_codigo_nuevo_reemplaza_el_anterior(): void
    {
        $usuario = User::factory()->create();
        $this->app->instance(AccountCodeSender::class, $this->gatewayFalso());
        $token = JWTAuth::fromUser($usuario);

        $this->withToken($token)->postJson(self::URI)->assertNoContent();
        $this->withToken($token)->postJson(self::URI)->assertNoContent();

        $this->assertDatabaseCount('phone_verification_codes', 1);
    }

    public function test_respeta_el_limite_de_tres_solicitudes_cada_diez_minutos(): void
    {
        $usuario = User::factory()->create();
        $this->app->instance(AccountCodeSender::class, $this->gatewayFalso());
        $token = JWTAuth::fromUser($usuario);

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($token)->postJson(self::URI)->assertNoContent();
        }

        $this->withToken($token)->postJson(self::URI)->assertStatus(429);
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $this->postJson(self::URI)->assertUnauthorized();

        $this->assertDatabaseCount('phone_verification_codes', 0);
    }
}
