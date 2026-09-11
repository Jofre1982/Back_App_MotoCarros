<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CargoJobType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de DELETE /api/v1/admin/cargo-job-types/{cargoJobType} — ver
 * openapi.yaml.
 */
class DeleteCargoJobTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_borra_un_tipo_de_acarreo(): void
    {
        $tipo = CargoJobType::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->deleteJson("/api/v1/admin/cargo-job-types/{$tipo->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cargo_job_types', ['id' => $tipo->id]);
    }

    public function test_responde_404_si_el_tipo_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->deleteJson('/api/v1/admin/cargo-job-types/999999')
            ->assertNotFound();
    }

    public function test_un_pasajero_no_puede_borrar_tipos_de_acarreo(): void
    {
        $tipo = CargoJobType::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->deleteJson("/api/v1/admin/cargo-job-types/{$tipo->id}")
            ->assertForbidden();
    }

    public function test_rechaza_el_borrado_sin_token(): void
    {
        $tipo = CargoJobType::factory()->create();

        $this->deleteJson("/api/v1/admin/cargo-job-types/{$tipo->id}")->assertUnauthorized();
    }
}
