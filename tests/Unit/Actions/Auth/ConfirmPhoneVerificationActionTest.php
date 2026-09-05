<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\ConfirmPhoneVerificationAction;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmPhoneVerificationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marca_el_celular_como_verificado_y_borra_el_codigo(): void
    {
        $usuario = User::factory()->create();
        PhoneVerificationCode::factory()->create(['user_id' => $usuario->id]);

        $resultado = (new ConfirmPhoneVerificationAction)->handle($usuario);

        $this->assertNotNull($resultado->phone_verified_at);
        $this->assertSame(0, PhoneVerificationCode::query()->count());
    }
}
