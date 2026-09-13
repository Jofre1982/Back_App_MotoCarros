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
 * Contrato de POST /api/v1/errands/{id}/accept — ver openapi.yaml.
 *
 * Historia #92: un conductor acepta un mandado disponible con el precio que
 * negoció con el pasajero por fuera del sistema.
 */
class AcceptErrandTest extends TestCase
{
    use RefreshDatabase;

    private function uri(Errand $mandado): string
    {
        return "/api/v1/errands/{$mandado->id}/accept";
    }

    public function test_el_conductor_acepta_un_mandado_disponible_con_el_precio_negociado(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create(['status' => ErrandStatus::Requested]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($mandado), ['agreed_price' => 15000])
            ->assertOk();

        $respuesta->assertJsonPath('data.status', 'accepted');
        $respuesta->assertJsonPath('data.agreed_price', 15000);
        $respuesta->assertJsonPath('data.driver.id', $conductor->id);

        $this->assertDatabaseHas('errands', [
            'id' => $mandado->id,
            'status' => 'accepted',
            'driver_id' => $conductor->id,
            'agreed_price' => 15000,
        ]);
    }

    public function test_rechaza_un_mandado_ya_aceptado_por_otro_conductor(): void
    {
        $primerConductor = User::factory()->driver()->create();
        $segundoConductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create([
            'status' => ErrandStatus::Accepted,
            'driver_id' => $primerConductor->id,
            'agreed_price' => 10000,
        ]);

        $this->withToken(JWTAuth::fromUser($segundoConductor))
            ->postJson($this->uri($mandado), ['agreed_price' => 20000])
            ->assertStatus(409);

        $this->assertDatabaseHas('errands', [
            'id' => $mandado->id,
            'driver_id' => $primerConductor->id,
            'agreed_price' => 10000,
        ]);
    }

    public function test_rechaza_la_entrada_sin_precio_acordado(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create(['status' => ErrandStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($mandado), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agreed_price');
    }

    public function test_un_pasajero_no_puede_aceptar_un_mandado(): void
    {
        $pasajero = User::factory()->create();
        $mandado = Errand::factory()->create(['status' => ErrandStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->postJson($this->uri($mandado), ['agreed_price' => 15000])
            ->assertForbidden();

        $this->assertDatabaseHas('errands', ['id' => $mandado->id, 'status' => 'requested']);
    }

    public function test_responde_404_cuando_el_mandado_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson('/api/v1/errands/999999/accept', ['agreed_price' => 15000])
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $mandado = Errand::factory()->create(['status' => ErrandStatus::Requested]);

        $this->postJson($this->uri($mandado), ['agreed_price' => 15000])
            ->assertUnauthorized();
    }
}
