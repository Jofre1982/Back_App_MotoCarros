<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CargoJobType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/admin/cargo-job-types — ver openapi.yaml.
 */
class ListCargoJobTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_los_tipos_de_acarreo(): void
    {
        CargoJobType::factory()->create(['name' => 'Acarreo', 'price' => 20000]);
        CargoJobType::factory()->create(['name' => 'Escombro', 'price' => 40000]);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->getJson('/api/v1/admin/cargo-job-types')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Acarreo')
            ->assertJsonPath('data.0.price', 20000)
            ->assertJsonPath('data.1.name', 'Escombro')
            ->assertJsonPath('data.1.price', 40000);
    }

    public function test_responde_lista_vacia_si_no_hay_tipos(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->getJson('/api/v1/admin/cargo-job-types')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_un_pasajero_no_puede_listar_tipos_de_acarreo(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson('/api/v1/admin/cargo-job-types')
            ->assertForbidden();
    }

    public function test_rechaza_la_lista_sin_token(): void
    {
        $this->getJson('/api/v1/admin/cargo-job-types')->assertUnauthorized();
    }
}
