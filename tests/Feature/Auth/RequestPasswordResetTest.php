<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\AccountCodeMail;
use App\Models\User;
use App\Services\Codes\AccountCodeSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakeAccountCodeSender;
use Tests\TestCase;

/**
 * Contrato de POST /api/v1/auth/password/forgot — ver openapi.yaml.
 *
 * Lo que fija esta suite, además del flujo feliz, es que un correo o
 * celular sin cuenta responda exactamente igual (204, sin envío) que uno con
 * cuenta: es lo que evita que el endpoint sea un oráculo de quién está
 * registrado.
 */
class RequestPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/auth/password/forgot';

    private function fake(): FakeAccountCodeSender
    {
        $fake = new FakeAccountCodeSender;
        $this->app->instance(AccountCodeSender::class, $fake);

        return $fake;
    }

    public function test_por_correo_envia_el_codigo_al_correo_de_la_cuenta(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $fake = $this->fake();

        $this->postJson(self::URI, ['email' => 'ANA@example.com'])->assertNoContent();

        $this->assertDatabaseCount('password_reset_codes', 1);
        $this->assertSame('ana@example.com', $fake->lastEmail());
        $this->assertSame('recuperar tu contraseña', $fake->sent[0]['purpose']);
    }

    public function test_por_celular_tambien_envia_el_codigo_al_correo(): void
    {
        User::factory()->create(['phone' => '+573001234567', 'email' => 'ana@example.com']);
        $fake = $this->fake();

        $this->postJson(self::URI, ['phone' => '573001234567'])->assertNoContent();

        $this->assertSame('ana@example.com', $fake->lastEmail());
    }

    public function test_el_correo_real_lleva_el_codigo(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson(self::URI, ['email' => 'ana@example.com'])->assertNoContent();

        Mail::assertSent(AccountCodeMail::class, fn (AccountCodeMail $mail): bool => $mail->hasTo('ana@example.com')
            && preg_match('/^[0-9]{6}$/', $mail->code) === 1);
    }

    public function test_un_correo_o_celular_sin_cuenta_responde_igual_que_uno_con_cuenta(): void
    {
        $fake = $this->fake();

        $this->postJson(self::URI, ['email' => 'nadie@example.com'])->assertNoContent();
        $this->postJson(self::URI, ['phone' => '+573009999999'])->assertNoContent();

        $this->assertDatabaseCount('password_reset_codes', 0);
        $this->assertSame([], $fake->sent);
    }

    public function test_pedir_un_codigo_nuevo_reemplaza_el_anterior(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $this->fake();

        $this->postJson(self::URI, ['email' => 'ana@example.com'])->assertNoContent();
        $this->postJson(self::URI, ['email' => 'ana@example.com'])->assertNoContent();

        $this->assertDatabaseCount('password_reset_codes', 1);
    }

    public function test_rechaza_un_correo_o_celular_mal_formado(): void
    {
        $this->postJson(self::URI, ['email' => 'no-es-un-correo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson(self::URI, ['phone' => 'no-es-un-celular'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_rechaza_sin_correo_ni_celular(): void
    {
        $this->postJson(self::URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_respeta_el_limite_de_tres_solicitudes_cada_diez_minutos_por_ip(): void
    {
        $this->fake();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::URI, ['email' => 'nadie@example.com'])->assertNoContent();
        }

        $this->postJson(self::URI, ['email' => 'nadie@example.com'])->assertStatus(429);
    }
}
