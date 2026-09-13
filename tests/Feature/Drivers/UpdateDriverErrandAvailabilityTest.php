<?php

declare(strict_types=1);

namespace Tests\Feature\Drivers;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de PATCH /api/v1/me/availability/errands — ver openapi.yaml.
 *
 * Historia #92: pool de disponibilidad separado del de
 * `PATCH /me/availability` (viajes de pasajero).
 */
class UpdateDriverErrandAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/availability/errands';

    public function test_el_conductor_se_marca_disponible_para_mandados(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['is_available_for_errands' => true])
            ->assertOk();

        $respuesta->assertJsonPath('data.is_available_for_errands', true);

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $conductor->id,
            'is_available_for_errands' => true,
        ]);
    }

    public function test_no_afecta_la_disponibilidad_de_viajes(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->available()->create(['user_id' => $conductor->id]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['is_available_for_errands' => true])
            ->assertOk();

        $respuesta->assertJsonPath('data.is_available', true);
        $respuesta->assertJsonPath('data.is_available_for_errands', true);
    }

    public function test_rechaza_la_entrada_sin_el_campo(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_available_for_errands');
    }

    public function test_un_pasajero_no_puede_marcarse_disponible(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson(self::URI, ['is_available_for_errands' => true])
            ->assertForbidden();
    }

    public function test_responde_404_sin_perfil_de_conductor(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->patchJson(self::URI, ['is_available_for_errands' => true])
            ->assertNotFound();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $this->patchJson(self::URI, ['is_available_for_errands' => true])
            ->assertUnauthorized();
    }
}
