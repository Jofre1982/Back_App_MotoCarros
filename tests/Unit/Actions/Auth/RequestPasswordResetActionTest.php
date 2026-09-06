<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\RequestPasswordResetAction;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestPasswordResetActionTest extends TestCase
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

        (new RequestPasswordResetAction($gateway))->handle('+573001234567');

        $codigo = PasswordResetCode::query()->where('user_id', $usuario->id)->firstOrFail();
        $this->assertSame(0, $codigo->attempts);
        $this->assertTrue($codigo->expires_at->isFuture());
        $this->assertSame('+573001234567', $gateway->phone);
        $this->assertNotNull($gateway->message);
    }

    public function test_pedir_un_codigo_nuevo_reemplaza_el_hash_anterior(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);
        $gateway = new class implements SmsGateway
        {
            public function send(string $phone, string $message): void {}
        };
        $action = new RequestPasswordResetAction($gateway);

        $action->handle('+573001234567');
        $primerHash = PasswordResetCode::query()->where('user_id', $usuario->id)->firstOrFail()->code_hash;

        $action->handle('+573001234567');
        $segundoHash = PasswordResetCode::query()->where('user_id', $usuario->id)->firstOrFail()->code_hash;

        $this->assertSame(1, PasswordResetCode::query()->count());
        $this->assertNotSame($primerHash, $segundoHash);
    }

    /**
     * El caso real que motiva esta suite: un celular sin cuenta no puede
     * distinguirse desde afuera de uno con cuenta, así que no debe escribir
     * ninguna fila ni intentar mandar nada.
     */
    public function test_un_celular_sin_cuenta_no_escribe_nada_ni_envia_nada(): void
    {
        $gateway = new class implements SmsGateway
        {
            public int $envios = 0;

            public function send(string $phone, string $message): void
            {
                $this->envios++;
            }
        };

        (new RequestPasswordResetAction($gateway))->handle('+573009999999');

        $this->assertSame(0, PasswordResetCode::query()->count());
        $this->assertSame(0, $gateway->envios);
    }
}
