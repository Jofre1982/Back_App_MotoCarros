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
 * Contrato de POST /api/v1/errands/{id}/complete — ver openapi.yaml.
 *
 * Historia #92: el conductor asignado marca el mandado como completado, con
 * el precio que registró al aceptarlo.
 */
class CompleteErrandTest extends TestCase
{
    use RefreshDatabase;

    private function uri(Errand $mandado): string
    {
        return "/api/v1/errands/{$mandado->id}/complete";
    }

    public function test_el_conductor_asignado_completa_el_mandado(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create([
            'status' => ErrandStatus::Accepted,
            'driver_id' => $conductor->id,
            'agreed_price' => 15000,
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($mandado))
            ->assertOk();

        $respuesta->assertJsonPath('data.status', 'completed');
        $respuesta->assertJsonPath('data.agreed_price', 15000);

        $this->assertDatabaseHas('errands', ['id' => $mandado->id, 'status' => 'completed']);
    }

    public function test_rechaza_un_mandado_sin_conductor_asignado(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create(['status' => ErrandStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($mandado))
            ->assertForbidden();
    }

    public function test_rechaza_completar_un_mandado_ya_completado(): void
    {
        $conductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create([
            'status' => ErrandStatus::Completed,
            'driver_id' => $conductor->id,
            'agreed_price' => 15000,
            'completed_at' => now(),
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson($this->uri($mandado))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('errand');
    }

    public function test_rechaza_a_un_conductor_que_no_es_el_asignado(): void
    {
        $asignado = User::factory()->driver()->create();
        $otroConductor = User::factory()->driver()->create();
        $mandado = Errand::factory()->create([
            'status' => ErrandStatus::Accepted,
            'driver_id' => $asignado->id,
            'agreed_price' => 15000,
        ]);

        $this->withToken(JWTAuth::fromUser($otroConductor))
            ->postJson($this->uri($mandado))
            ->assertForbidden();

        $this->assertDatabaseHas('errands', ['id' => $mandado->id, 'status' => 'accepted']);
    }

    public function test_responde_404_cuando_el_mandado_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson('/api/v1/errands/999999/complete')
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $mandado = Errand::factory()->create(['status' => ErrandStatus::Accepted]);

        $this->postJson($this->uri($mandado))->assertUnauthorized();
    }
}
