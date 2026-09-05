<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Realtime;

use App\Actions\Realtime\RegisterDeviceTokenAction;
use App\DTOs\DeviceTokenRegistration;
use App\Enums\DevicePlatform;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterDeviceTokenActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_token_asociado_al_usuario(): void
    {
        $usuario = User::factory()->create();

        $token = (new RegisterDeviceTokenAction)->handle(
            $usuario,
            new DeviceTokenRegistration(token: 'abc', platform: DevicePlatform::Android),
        );

        $this->assertSame($usuario->id, $token->user_id);
        $this->assertSame(DevicePlatform::Android, $token->platform);
    }

    public function test_registrar_el_mismo_token_dos_veces_no_lo_duplica(): void
    {
        $usuario = User::factory()->create();
        $action = new RegisterDeviceTokenAction;

        $action->handle($usuario, new DeviceTokenRegistration('abc', DevicePlatform::Android));
        $action->handle($usuario, new DeviceTokenRegistration('abc', DevicePlatform::Ios));

        $this->assertSame(1, DeviceToken::query()->count());
        $this->assertSame(DevicePlatform::Ios, DeviceToken::query()->firstOrFail()->platform);
    }

    public function test_el_mismo_token_puede_mudarse_de_una_cuenta_a_otra(): void
    {
        $primero = User::factory()->create();
        $segundo = User::factory()->create();
        $action = new RegisterDeviceTokenAction;

        $action->handle($primero, new DeviceTokenRegistration('compartido', DevicePlatform::Android));
        $action->handle($segundo, new DeviceTokenRegistration('compartido', DevicePlatform::Android));

        $this->assertSame(1, DeviceToken::query()->count());
        $this->assertSame($segundo->id, DeviceToken::query()->firstOrFail()->user_id);
    }
}
