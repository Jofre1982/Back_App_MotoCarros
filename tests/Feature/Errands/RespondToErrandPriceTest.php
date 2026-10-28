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
 * Contrato de POST /api/v1/errands/{id}/price-response — ver openapi.yaml.
 */
class RespondToErrandPriceTest extends TestCase
{
    use RefreshDatabase;

    private function mandadoConOferta(User $pasajero, ?User $conductor = null): Errand
    {
        return Errand::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => ($conductor ?? User::factory()->driver()->create())->id,
            'status' => ErrandStatus::Accepted,
            'agreed_price' => 9000,
        ]);
    }

    private function cambiarDeCuenta(): void
    {
        $this->app->forgetInstance('tymon.jwt');
        $this->app['auth']->forgetGuards();
    }

    public function test_al_aceptar_queda_confirmado_y_el_conductor_puede_completarlo(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create();
        $mandado = $this->mandadoConOferta($pasajero, $conductor);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/errands/{$mandado->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['errand']);

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson("/api/v1/errands/{$mandado->id}/price-response", ['decision' => 'accept'])
            ->assertOk()
            ->assertJsonPath('data.price_confirmed', true)
            ->assertJsonPath('data.agreed_price', 9000);

        $this->cambiarDeCuenta();
        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/errands/{$mandado->id}/complete")
            ->assertOk();
    }

    public function test_al_rechazar_vuelve_a_quedar_libre(): void
    {
        $pasajero = User::factory()->create();
        $mandado = $this->mandadoConOferta($pasajero);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson("/api/v1/errands/{$mandado->id}/price-response", ['decision' => 'reject'])
            ->assertOk()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.agreed_price', null)
            ->assertJsonPath('data.price_confirmed', false);
    }

    public function test_no_responde_un_mandado_sin_oferta_o_ya_confirmado(): void
    {
        $pasajero = User::factory()->create();
        $token = JWTAuth::fromUser($pasajero);
        $libre = Errand::factory()->create(['passenger_id' => $pasajero->id]);

        $this->withToken($token)->postJson("/api/v1/errands/{$libre->id}/price-response", ['decision' => 'accept'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['errand']);

        $conOferta = $this->mandadoConOferta($pasajero);
        $this->withToken($token)->postJson("/api/v1/errands/{$conOferta->id}/price-response", ['decision' => 'accept'])->assertOk();
        $this->withToken($token)->postJson("/api/v1/errands/{$conOferta->id}/price-response", ['decision' => 'reject'])
            ->assertUnprocessable();
    }

    public function test_solo_el_pasajero_duenio_responde(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = $this->mandadoConOferta(User::factory()->create(), $conductor);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson("/api/v1/errands/{$mandado->id}/price-response", ['decision' => 'accept'])
            ->assertForbidden();
    }
}
