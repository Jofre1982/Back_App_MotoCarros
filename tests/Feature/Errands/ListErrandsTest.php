<?php

declare(strict_types=1);

namespace Tests\Feature\Errands;

use App\Enums\ErrandStatus;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/errands — ver openapi.yaml.
 *
 * Historia #92: la lista de mandados disponibles solo la ve un conductor
 * marcado disponible **para mandados** — pool separado del de viajes.
 */
class ListErrandsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/errands';

    public function test_un_conductor_disponible_para_mandados_ve_los_requested(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id, 'is_available_for_errands' => true]);
        $mandado = Errand::factory()->create(['status' => ErrandStatus::Requested]);
        Errand::factory()->create(['status' => ErrandStatus::Accepted]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk();

        $respuesta->assertJsonCount(1, 'data');
        $respuesta->assertJsonPath('data.0.id', $mandado->id);
    }

    public function test_un_conductor_disponible_solo_para_viajes_no_ve_mandados(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $conductor->id]);
        Errand::factory()->create(['status' => ErrandStatus::Requested]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk();

        $respuesta->assertJsonCount(0, 'data');
    }

    public function test_un_conductor_sin_perfil_no_ve_mandados(): void
    {
        $conductor = User::factory()->driver()->create();
        Errand::factory()->create(['status' => ErrandStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_un_pasajero_no_puede_listar_mandados(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(self::URI)
            ->assertForbidden();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }
}
