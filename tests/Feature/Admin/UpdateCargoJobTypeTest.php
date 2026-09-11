<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CargoJobType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de PATCH /api/v1/admin/cargo-job-types/{cargoJobType} — ver
 * openapi.yaml.
 */
class UpdateCargoJobTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_actualiza_el_precio(): void
    {
        $tipo = CargoJobType::factory()->create(['name' => 'Acarreo', 'price' => 20000]);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->patchJson("/api/v1/admin/cargo-job-types/{$tipo->id}", ['price' => 25000])
            ->assertOk()
            ->assertJsonPath('data.price', 25000)
            ->assertJsonPath('data.name', 'Acarreo');

        $this->assertDatabaseHas('cargo_job_types', ['id' => $tipo->id, 'price' => 25000]);
    }

    public function test_rechaza_un_precio_negativo(): void
    {
        $tipo = CargoJobType::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->patchJson("/api/v1/admin/cargo-job-types/{$tipo->id}", ['price' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price');
    }

    public function test_responde_404_si_el_tipo_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->patchJson('/api/v1/admin/cargo-job-types/999999', ['price' => 25000])
            ->assertNotFound();
    }

    public function test_un_pasajero_no_puede_editar_tipos_de_acarreo(): void
    {
        $tipo = CargoJobType::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->patchJson("/api/v1/admin/cargo-job-types/{$tipo->id}", ['price' => 25000])
            ->assertForbidden();
    }
}
