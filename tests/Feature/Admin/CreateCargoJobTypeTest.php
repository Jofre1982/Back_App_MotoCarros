<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CargoJobType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/admin/cargo-job-types — ver openapi.yaml.
 */
class CreateCargoJobTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_tipo_de_acarreo(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/cargo-job-types', ['name' => 'Trasteo', 'price' => 20000])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Trasteo')
            ->assertJsonPath('data.price', 20000);

        $this->assertDatabaseHas('cargo_job_types', ['name' => 'Trasteo', 'price' => 20000]);
    }

    public function test_rechaza_un_nombre_repetido(): void
    {
        CargoJobType::factory()->create(['name' => 'Trasteo']);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/cargo-job-types', ['name' => 'Trasteo', 'price' => 20000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_rechaza_un_precio_negativo(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/cargo-job-types', ['name' => 'Trasteo', 'price' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price');
    }

    public function test_un_pasajero_no_puede_crear_tipos_de_acarreo(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/admin/cargo-job-types', ['name' => 'Trasteo', 'price' => 20000])
            ->assertForbidden();
    }

    public function test_rechaza_la_creacion_sin_token(): void
    {
        $this->postJson('/api/v1/admin/cargo-job-types', ['name' => 'Trasteo', 'price' => 20000])
            ->assertUnauthorized();
    }
}
