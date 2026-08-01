<?php

declare(strict_types=1);

namespace Tests\Feature\Rides;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/me/rides — ver openapi.yaml.
 *
 * Historia #29: el pasajero consulta y audita sus viajes pasados. El mismo
 * path servirá el historial del conductor (historia #30), pero eso es fuera
 * de alcance acá.
 */
class ShowRideHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pasajero_ve_su_historial_ordenado_del_mas_reciente_al_mas_antiguo(): void
    {
        $pasajero = User::factory()->create();

        $viejo = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Completed,
            'created_at' => now()->subDays(2),
        ]);
        $reciente = Ride::factory()->for($pasajero, 'passenger')->create([
            'status' => RideStatus::Requested,
            'created_at' => now()->subHour(),
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson('/api/v1/me/rides')
            ->assertOk();

        $respuesta->assertJsonPath('data.0.id', $reciente->id);
        $respuesta->assertJsonPath('data.1.id', $viejo->id);
        $respuesta->assertJsonPath('data.0.status', RideStatus::Requested->value);
        $respuesta->assertJsonPath('data.1.status', RideStatus::Completed->value);
        $respuesta->assertJsonPath('data.1.estimated_fare', $viejo->estimated_fare);
    }

    public function test_un_pasajero_sin_viajes_recibe_una_lista_vacia_con_200(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson('/api/v1/me/rides')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_no_incluye_viajes_de_otro_pasajero(): void
    {
        $pasajero = User::factory()->create();
        Ride::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson('/api/v1/me/rides')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_respeta_los_parametros_de_paginacion(): void
    {
        // Estado `completed` y no el `requested` por defecto de la factory:
        // `active_passenger_id` es único por pasajero mientras el viaje sigue
        // activo (ver la migración de `rides`), así que varios viajes
        // simultáneos del mismo pasajero solo caben si ya terminaron.
        $pasajero = User::factory()->create();
        Ride::factory()->for($pasajero, 'passenger')->count(5)->create([
            'status' => RideStatus::Completed,
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson('/api/v1/me/rides?page=2&per_page=2')
            ->assertOk();

        $respuesta->assertJsonCount(2, 'data');
        $respuesta->assertJsonPath('meta.current_page', 2);
        $respuesta->assertJsonPath('meta.per_page', 2);
        $respuesta->assertJsonPath('meta.total', 5);
        $respuesta->assertJsonPath('meta.last_page', 3);
    }

    /**
     * Pedir un `per_page` desmedido no es una entrada inválida: el backend lo
     * acota a un límite razonable en vez de responder 422 (ver
     * `ShowRideHistoryController::MAX_PER_PAGE`).
     */
    public function test_acota_un_per_page_desmedido_a_un_limite_razonable(): void
    {
        $pasajero = User::factory()->create();
        Ride::factory()->for($pasajero, 'passenger')->count(3)->create([
            'status' => RideStatus::Completed,
        ]);

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson('/api/v1/me/rides?per_page=1000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_rechaza_un_per_page_que_no_es_un_entero(): void
    {
        $pasajero = User::factory()->create();

        $this->withToken(JWTAuth::fromUser($pasajero))
            ->getJson('/api/v1/me/rides?per_page=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_rechaza_a_un_conductor(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson('/api/v1/me/rides')
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $this->getJson('/api/v1/me/rides')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }
}
