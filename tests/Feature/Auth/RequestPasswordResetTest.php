<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato de POST /api/v1/auth/password/forgot — ver openapi.yaml.
 *
 * Lo que fija esta suite, además del flujo feliz, es que un celular sin
 * cuenta responda exactamente igual (204, sin SMS) que uno con cuenta: es lo
 * que evita que el endpoint sea un oráculo de qué números están registrados.
 */
class RequestPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/auth/password/forgot';

    private function gatewayFalso(): SmsGateway
    {
        return new class implements SmsGateway
        {
            public function send(string $phone, string $message): void {}
        };
    }

    public function test_genera_un_codigo_y_lo_envia_por_sms_a_una_cuenta_existente(): void
    {
        User::factory()->create(['phone' => '+573001234567']);

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

        $this->postJson(self::URI, ['phone' => '+573001234567'])->assertNoContent();

        $this->assertDatabaseCount('password_reset_codes', 1);
        $this->assertSame('+573001234567', $gateway->phone);
        $this->assertNotNull($gateway->message);
    }

    public function test_un_celular_sin_cuenta_responde_igual_que_uno_con_cuenta(): void
    {
        $this->app->instance(SmsGateway::class, $this->gatewayFalso());

        $this->postJson(self::URI, ['phone' => '+573009999999'])->assertNoContent();

        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_normaliza_el_celular_sin_el_signo_mas_antes_de_buscar_la_cuenta(): void
    {
        User::factory()->create(['phone' => '+573001234567']);

        $gateway = new class implements SmsGateway
        {
            public ?string $phone = null;

            public function send(string $phone, string $message): void
            {
                $this->phone = $phone;
            }
        };
        $this->app->instance(SmsGateway::class, $gateway);

        $this->postJson(self::URI, ['phone' => '573001234567'])->assertNoContent();

        $this->assertSame('+573001234567', $gateway->phone);
    }

    public function test_pedir_un_codigo_nuevo_reemplaza_el_anterior(): void
    {
        User::factory()->create(['phone' => '+573001234567']);
        $this->app->instance(SmsGateway::class, $this->gatewayFalso());

        $this->postJson(self::URI, ['phone' => '+573001234567'])->assertNoContent();
        $this->postJson(self::URI, ['phone' => '+573001234567'])->assertNoContent();

        $this->assertDatabaseCount('password_reset_codes', 1);
    }

    public function test_rechaza_un_celular_mal_formado(): void
    {
        $this->postJson(self::URI, ['phone' => 'no-es-un-celular'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_rechaza_sin_celular_en_el_body(): void
    {
        $this->postJson(self::URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_respeta_el_limite_de_tres_solicitudes_cada_diez_minutos_por_ip(): void
    {
        $this->app->instance(SmsGateway::class, $this->gatewayFalso());

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::URI, ['phone' => '+573009999999'])->assertNoContent();
        }

        $this->postJson(self::URI, ['phone' => '+573009999999'])->assertStatus(429);
    }
}
