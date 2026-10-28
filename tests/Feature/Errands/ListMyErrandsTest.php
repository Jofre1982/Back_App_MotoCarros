<?php

declare(strict_types=1);

namespace Tests\Feature\Errands;

use App\Enums\ErrandStatus;
use App\Models\DriverProfile;
use App\Models\Errand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/me/errands — ver openapi.yaml.
 */
class ListMyErrandsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/errands';

    public function test_el_pasajero_ve_sus_mandados_del_mas_nuevo_al_mas_viejo_con_el_precio_acordado(): void
    {
        $pasajero = User::factory()->create();
        $conductor = User::factory()->driver()->create(['name' => 'Luis Conductor']);
        $viejo = Errand::factory()->create(['passenger_id' => $pasajero->id, 'created_at' => Carbon::parse('2026-10-28 09:00')]);
        $aceptado = Errand::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => ErrandStatus::Accepted,
            'agreed_price' => 7000,
            'created_at' => Carbon::parse('2026-10-28 10:00'),
        ]);
        Errand::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $aceptado->id)
            ->assertJsonPath('data.0.status', 'accepted')
            ->assertJsonPath('data.0.agreed_price', 7000)
            ->assertJsonPath('data.0.driver.name', 'Luis Conductor')
            ->assertJsonPath('data.1.id', $viejo->id);
    }

    public function test_el_conductor_ve_los_que_le_asignaron(): void
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);
        $suyo = Errand::factory()->create(['driver_id' => $conductor->id, 'status' => ErrandStatus::Accepted]);
        Errand::factory()->create(['status' => ErrandStatus::Requested]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI.'?as=driver')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $suyo->id);
    }

    public function test_un_pasajero_no_puede_pedir_la_vista_de_conductor(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(self::URI.'?as=driver')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['as']);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }
}
