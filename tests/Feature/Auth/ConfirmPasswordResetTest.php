<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/auth/password/reset — ver openapi.yaml.
 */
class ConfirmPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/auth/password/reset';

    public function test_cambia_la_contrasena_con_el_codigo_correcto_y_deja_la_cuenta_autenticada(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);
        PasswordResetCode::factory()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $respuesta = $this->postJson(self::URI, [
            'phone' => '+573001234567',
            'code' => '123456',
            'password' => 'nuevaClave2026',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $usuario->id);

        $this->assertTrue(Hash::check('nuevaClave2026', $usuario->fresh()->password));
        $this->assertDatabaseCount('password_reset_codes', 0);

        $token = $respuesta->json('data.token.access_token');
        $identificado = JWTAuth::setToken($token)->authenticate();
        $this->assertSame($usuario->id, $identificado?->id);
    }

    public function test_normaliza_el_celular_sin_el_signo_mas_antes_de_buscar_la_cuenta(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);
        PasswordResetCode::factory()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $this->postJson(self::URI, [
            'phone' => '573001234567',
            'code' => '123456',
            'password' => 'nuevaClave2026',
        ])->assertOk();

        $this->assertTrue(Hash::check('nuevaClave2026', $usuario->fresh()->password));
    }

    public function test_rechaza_un_codigo_incorrecto_y_cuenta_el_intento(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);
        $reset = PasswordResetCode::factory()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $this->postJson(self::URI, [
            'phone' => '+573001234567',
            'code' => '000000',
            'password' => 'nuevaClave2026',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertSame(1, $reset->fresh()->attempts);
        $this->assertTrue(Hash::check('password', $usuario->fresh()->password));
    }

    public function test_rechaza_un_codigo_vencido(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);
        PasswordResetCode::factory()->expired()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $this->postJson(self::URI, [
            'phone' => '+573001234567',
            'code' => '123456',
            'password' => 'nuevaClave2026',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_rechaza_cuando_se_agotaron_los_intentos(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);
        PasswordResetCode::factory()->exhausted()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $this->postJson(self::URI, [
            'phone' => '+573001234567',
            'code' => '123456',
            'password' => 'nuevaClave2026',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_rechaza_sin_ninguna_recuperacion_pendiente(): void
    {
        User::factory()->create(['phone' => '+573001234567']);

        $this->postJson(self::URI, [
            'phone' => '+573001234567',
            'code' => '123456',
            'password' => 'nuevaClave2026',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    /**
     * Mismo mensaje genérico que "sin ninguna recuperación pendiente", para
     * que un celular sin cuenta no se distinga de uno con cuenta pero sin
     * código pendiente — es la misma protección de
     * `RequestPasswordResetAction` vista desde este lado del flujo.
     */
    public function test_rechaza_un_celular_sin_cuenta_con_el_mismo_mensaje_generico(): void
    {
        $this->postJson(self::URI, [
            'phone' => '+573009999999',
            'code' => '123456',
            'password' => 'nuevaClave2026',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_rechaza_una_contrasena_que_no_cumple_la_politica(): void
    {
        $usuario = User::factory()->create(['phone' => '+573001234567']);
        PasswordResetCode::factory()->create([
            'user_id' => $usuario->id,
            'code_hash' => Hash::make('123456'),
        ]);

        $this->postJson(self::URI, [
            'phone' => '+573001234567',
            'code' => '123456',
            'password' => 'corta1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_rechaza_un_celular_mal_formado(): void
    {
        $this->postJson(self::URI, [
            'phone' => 'no-es-un-celular',
            'code' => '123456',
            'password' => 'nuevaClave2026',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }
}
