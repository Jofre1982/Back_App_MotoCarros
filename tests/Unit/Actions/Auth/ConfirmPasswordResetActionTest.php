<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\ConfirmPasswordResetAction;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ConfirmPasswordResetActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambia_la_contrasena_y_borra_el_codigo(): void
    {
        $usuario = User::factory()->create();
        PasswordResetCode::factory()->create(['user_id' => $usuario->id]);

        $this->action()->handle($usuario, 'nuevaClave2026');

        $this->assertTrue(Hash::check('nuevaClave2026', $usuario->fresh()->password));
        $this->assertSame(0, PasswordResetCode::query()->count());
    }

    public function test_devuelve_un_token_que_identifica_a_la_cuenta(): void
    {
        $usuario = User::factory()->create();
        PasswordResetCode::factory()->create(['user_id' => $usuario->id]);

        $resultado = $this->action()->handle($usuario, 'nuevaClave2026');

        $identificado = JWTAuth::setToken($resultado->token->accessToken)->authenticate();

        $this->assertInstanceOf(User::class, $identificado);
        $this->assertSame($usuario->id, $identificado->id);
    }

    private function action(): ConfirmPasswordResetAction
    {
        return $this->app->make(ConfirmPasswordResetAction::class);
    }
}
