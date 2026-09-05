<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\RequestPhoneVerificationAction;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestPhoneVerificationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_codigo_asociado_a_la_cuenta_y_lo_envia(): void
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

        (new RequestPhoneVerificationAction($gateway))->handle($usuario);

        $codigo = PhoneVerificationCode::query()->where('user_id', $usuario->id)->firstOrFail();
        $this->assertSame(0, $codigo->attempts);
        $this->assertTrue($codigo->expires_at->isFuture());
        $this->assertSame('+573001234567', $gateway->phone);
        $this->assertNotNull($gateway->message);
    }

    public function test_pedir_un_codigo_nuevo_reemplaza_el_hash_anterior(): void
    {
        $usuario = User::factory()->create();
        $gateway = new class implements SmsGateway
        {
            public function send(string $phone, string $message): void {}
        };
        $action = new RequestPhoneVerificationAction($gateway);

        $action->handle($usuario);
        $primerHash = PhoneVerificationCode::query()->where('user_id', $usuario->id)->firstOrFail()->code_hash;

        $action->handle($usuario);
        $segundoHash = PhoneVerificationCode::query()->where('user_id', $usuario->id)->firstOrFail()->code_hash;

        $this->assertSame(1, PhoneVerificationCode::query()->count());
        $this->assertNotSame($primerHash, $segundoHash);
    }
}
