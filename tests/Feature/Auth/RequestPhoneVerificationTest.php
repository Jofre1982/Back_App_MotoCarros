<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/me/phone/verification — ver openapi.yaml.
 */
class RequestPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/phone/verification';

    private function gatewayFalso(): SmsGateway
    {
        return new class implements SmsGateway
        {
            public function send(string $phone, string $message): void {}
        };
    }

    public function test_genera_un_codigo_y_lo_envia_por_sms(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);

        $gateway = new class implements SmsGateway
        {
            public ?string $phone = null;

            public ?string $message = null;

            public function send(string $phone, string $message): void
            {
                $this->phone = $phone;
                $this->message = $message;
            }
        };
        $this->app->instance(SmsGateway::class, $gateway);

        $this->withToken(JWTAuth::fromUser($usuario))
            ->postJson(self::URI)
            ->assertNoContent();

        $this->assertDatabaseHas('phone_verification_codes', ['user_id' => $usuario->id]);
        $this->assertSame('+573001234567', $gateway->phone);
        $this->assertNotNull($gateway->message);
    }

    public function test_pedir_un_codigo_nuevo_reemplaza_el_anterior(): void
    {
        $usuario = User::factory()->create();
        $this->app->instance(SmsGateway::class, $this->gatewayFalso());
        $token = JWTAuth::fromUser($usuario);

        $this->withToken($token)->postJson(self::URI)->assertNoContent();
        $this->withToken($token)->postJson(self::URI)->assertNoContent();

        $this->assertDatabaseCount('phone_verification_codes', 1);
    }

    public function test_respeta_el_limite_de_tres_solicitudes_cada_diez_minutos(): void
    {
        $usuario = User::factory()->create();
        $this->app->instance(SmsGateway::class, $this->gatewayFalso());
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
