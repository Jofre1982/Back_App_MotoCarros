<?php

declare(strict_types=1);

namespace Tests\Feature\Errands;

use App\Enums\ErrandStatus;
use App\Models\Errand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/errands/{id}/cancel — ver openapi.yaml.
 */
class CancelErrandTest extends TestCase
{
    use RefreshDatabase;

    private function uri(Errand $mandado): string
    {
        return "/api/v1/errands/{$mandado->id}/cancel";
    }

    public function test_el_pasajero_cancela_un_mandado_que_nadie_acepto(): void
    {
        $pasajero = User::factory()->create();
        $mandado = Errand::factory()->create(['passenger_id' => $pasajero->id]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($mandado))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(ErrandStatus::Cancelled, $mandado->refresh()->status);
    }

    public function test_el_pasajero_cancela_un_mandado_ya_aceptado(): void
    {
        $pasajero = User::factory()->create();
        $mandado = Errand::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => User::factory()->driver()->create()->id,
            'status' => ErrandStatus::Accepted,
            'agreed_price' => 8000,
            'price_confirmed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($mandado))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_un_mandado_cancelado_no_aparece_para_los_conductores(): void
    {
        $pasajero = User::factory()->create();
        $mandado = Errand::factory()->create(['passenger_id' => $pasajero->id]);
        $this->withToken(JWTAuth::fromUser($pasajero))->postJson($this->uri($mandado))->assertOk();

        $this->assertSame(0, Errand::query()->where('status', ErrandStatus::Requested)->count());
    }

    public function test_el_conductor_asignado_lo_suelta_y_vuelve_a_la_lista(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create([
            'driver_id' => $conductor->id,
            'status' => ErrandStatus::Accepted,
            'agreed_price' => 8000,
            'price_confirmed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($mandado))
            ->assertOk()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.agreed_price', null);
    }

    public function test_no_se_cancela_un_mandado_terminado(): void
    {
        $pasajero = User::factory()->create();
        $token = JWTAuth::fromUser($pasajero);

        foreach ([ErrandStatus::Completed, ErrandStatus::Cancelled] as $estado) {
            $mandado = Errand::factory()->create(['passenger_id' => $pasajero->id, 'status' => $estado]);
            $this->withToken($token)
                ->postJson($this->uri($mandado))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['errand']);
        }
    }

    public function test_otra_cuenta_no_puede_cancelarlo(): void
    {
        $mandado = Errand::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson($this->uri($mandado))
            ->assertForbidden();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->postJson($this->uri(Errand::factory()->create()))->assertUnauthorized();
    }
}
