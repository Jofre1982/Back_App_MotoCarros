<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/admin/sites — ver openapi.yaml.
 */
class CreateSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_sitio(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/sites', ['name' => 'Vitina'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Vitina')
            ->assertJsonPath('data.fares', []);

        $this->assertDatabaseHas('sites', ['name' => 'Vitina']);
    }

    public function test_rechaza_un_nombre_repetido(): void
    {
        Site::factory()->create(['name' => 'Vitina']);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/sites', ['name' => 'Vitina'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_rechaza_un_nombre_vacio(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/sites', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_un_pasajero_no_puede_crear_sitios(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson('/api/v1/admin/sites', ['name' => 'Vitina'])
            ->assertForbidden();
    }

    public function test_rechaza_la_creacion_sin_token(): void
    {
        $this->postJson('/api/v1/admin/sites', ['name' => 'Vitina'])->assertUnauthorized();
    }
}
