<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\DriverVerificationStatus;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/admin/users — ver openapi.yaml (historia #97).
 */
class ListUsersTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/admin/users';

    public function test_el_admin_ve_los_usuarios_del_mas_reciente_al_mas_antiguo(): void
    {
        $admin = User::factory()->admin()->create(['created_at' => Carbon::parse('2026-10-01 10:00')]);
        $pasajero = User::factory()->create([
            'name' => 'Ana García',
            'created_at' => Carbon::parse('2026-10-02 10:00'),
        ]);
        $conductor = User::factory()->driver()->create(['created_at' => Carbon::parse('2026-10-03 10:00')]);
        DriverProfile::factory()->create([
            'user_id' => $conductor->id,
            'verification_status' => DriverVerificationStatus::Verified,
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($admin))
            ->getJson(self::URI)
            ->assertOk();

        $this->assertSame(
            [$conductor->id, $pasajero->id, $admin->id],
            array_column($respuesta->json('data'), 'id'),
        );
        $respuesta
            ->assertJsonPath('data.0.role', 'driver')
            ->assertJsonPath('data.0.driver_verification_status', 'verified')
            ->assertJsonPath('data.1.name', 'Ana García')
            ->assertJsonPath('data.1.email', $pasajero->email)
            ->assertJsonPath('data.1.phone', $pasajero->phone)
            ->assertJsonPath('data.1.role', 'passenger')
            ->assertJsonPath('data.1.driver_verification_status', null)
            ->assertJsonPath('data.1.registered_at', '2026-10-02T10:00:00+00:00')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.totals', ['passenger' => 1, 'driver' => 1, 'admin' => 1]);
    }

    public function test_nunca_expone_la_contrasena(): void
    {
        $admin = User::factory()->admin()->create();

        $respuesta = $this->withToken(JWTAuth::fromUser($admin))
            ->getJson(self::URI)
            ->assertOk();

        $this->assertStringNotContainsString('password', $respuesta->getContent() ?: '');
        $this->assertStringNotContainsString('remember_token', $respuesta->getContent() ?: '');
    }

    public function test_filtra_por_rol_sin_cambiar_los_totales(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($admin))
            ->getJson(self::URI.'?role=driver')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conductor->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.totals', ['passenger' => 2, 'driver' => 1, 'admin' => 1]);
    }

    public function test_busca_por_nombre_email_o_telefono(): void
    {
        $admin = User::factory()->admin()->create();
        $carlos = User::factory()->create(['name' => 'Carlos Pérez', 'email' => 'cperez@example.com', 'phone' => '+573001112233']);
        User::factory()->create(['name' => 'Luisa Gómez', 'email' => 'luisa@example.com', 'phone' => '+573009998877']);
        $token = JWTAuth::fromUser($admin);

        foreach (['carlos', 'cperez@', '1112233'] as $busqueda) {
            $this->withToken($token)
                ->getJson(self::URI.'?search='.urlencode($busqueda))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $carlos->id);
        }
    }

    public function test_pagina_de_a_cincuenta(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(55)->create();
        $token = JWTAuth::fromUser($admin);

        $this->withToken($token)
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 56);

        $this->withToken($token)
            ->getJson(self::URI.'?page=2')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_rechaza_un_rol_desconocido(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->getJson(self::URI.'?role=superusuario')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_un_pasajero_no_puede_ver_los_usuarios(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(self::URI)
            ->assertForbidden();
    }

    public function test_un_conductor_no_puede_ver_los_usuarios(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->getJson(self::URI)
            ->assertForbidden();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }
}
