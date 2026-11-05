<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/admin/users/{id}/gift-months — ver openapi.yaml.
 *
 * Pedido del dueño: regalar un mes a los 12 conductores que le ayudan con la
 * prueba cerrada de la Play Store, antes de que la suscripción se cobre.
 */
class GiftSubscriptionMonthsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 20:00:00');
    }

    public function test_regala_un_mes_desde_hoy(): void
    {
        $conductor = $this->conductor();

        $this->comoAdmin()
            ->postJson($this->uri($conductor), ['months' => 1])
            ->assertOk()
            ->assertJsonPath('data.id', $conductor->id)
            ->assertJsonPath('data.subscription_ends_at', '2026-12-05T20:00:00+00:00');
    }

    public function test_se_suma_a_lo_que_todavia_no_ha_vencido(): void
    {
        $conductor = $this->conductor(venceEl: '2026-11-20 08:00:00');

        $this->comoAdmin()
            ->postJson($this->uri($conductor), ['months' => 2])
            ->assertOk()
            ->assertJsonPath('data.subscription_ends_at', '2027-01-20T08:00:00+00:00');
    }

    public function test_si_ya_vencio_cuenta_desde_hoy(): void
    {
        $conductor = $this->conductor(venceEl: '2026-09-01 00:00:00');

        $this->comoAdmin()
            ->postJson($this->uri($conductor), ['months' => 1])
            ->assertOk()
            ->assertJsonPath('data.subscription_ends_at', '2026-12-05T20:00:00+00:00');
    }

    public function test_el_listado_de_usuarios_muestra_hasta_cuando(): void
    {
        $this->conductor(venceEl: '2026-12-01 00:00:00');

        $this->comoAdmin()
            ->getJson('/api/v1/admin/users?role=driver')
            ->assertOk()
            ->assertJsonPath('data.0.subscription_ends_at', '2026-12-01T00:00:00+00:00');
    }

    public function test_rechaza_meses_fuera_de_rango(): void
    {
        $conductor = $this->conductor();

        foreach ([0, 13, 'uno', null] as $meses) {
            $this->comoAdmin()
                ->postJson($this->uri($conductor), ['months' => $meses])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('months');
        }
    }

    public function test_rechaza_una_cuenta_sin_perfil_de_conductor(): void
    {
        $pasajero = User::factory()->create();

        $this->comoAdmin()
            ->postJson($this->uri($pasajero), ['months' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    }

    public function test_solo_el_admin_regala_meses(): void
    {
        $conductor = $this->conductor();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($conductor), ['months' => 12])
            ->assertForbidden();

        $this->assertNull($conductor->driverProfile()->firstOrFail()->subscription_ends_at);
    }

    public function test_cuenta_inexistente_404_y_sin_token_401(): void
    {
        $this->comoAdmin()->postJson('/api/v1/admin/users/999999/gift-months', ['months' => 1])->assertNotFound();

        $this->flushHeaders();
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/admin/users/1/gift-months', ['months' => 1])->assertUnauthorized();
    }

    private function conductor(?string $venceEl = null): User
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create([
            'user_id' => $conductor->id,
            'subscription_ends_at' => $venceEl,
        ]);

        return $conductor;
    }

    private function comoAdmin(): static
    {
        return $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()));
    }

    private function uri(User $user): string
    {
        return "/api/v1/admin/users/{$user->id}/gift-months";
    }
}
