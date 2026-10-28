<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\RequestPhoneVerificationAction;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeAccountCodeSender;
use Tests\TestCase;

class RequestPhoneVerificationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_codigo_asociado_a_la_cuenta_y_lo_envia(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);

        $gateway = new FakeAccountCodeSender;

        (new RequestPhoneVerificationAction($gateway))->handle($usuario);

        $codigo = PhoneVerificationCode::query()->where('user_id', $usuario->id)->firstOrFail();
        $this->assertSame(0, $codigo->attempts);
        $this->assertTrue($codigo->expires_at->isFuture());
        $this->assertSame($usuario->email, $gateway->lastEmail());
        $this->assertCount(1, $gateway->sent);
    }

    public function test_pedir_un_codigo_nuevo_reemplaza_el_hash_anterior(): void
    {
        $usuario = User::factory()->create();
        $gateway = new FakeAccountCodeSender;
        $action = new RequestPhoneVerificationAction($gateway);

        $action->handle($usuario);
        $primerHash = PhoneVerificationCode::query()->where('user_id', $usuario->id)->firstOrFail()->code_hash;

        $action->handle($usuario);
        $segundoHash = PhoneVerificationCode::query()->where('user_id', $usuario->id)->firstOrFail()->code_hash;

        $this->assertSame(1, PhoneVerificationCode::query()->count());
        $this->assertNotSame($primerHash, $segundoHash);
    }
}
