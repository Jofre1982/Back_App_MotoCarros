<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Site;
use App\Models\SiteFare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de DELETE /api/v1/admin/sites/{site} — ver openapi.yaml.
 */
class DeleteSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_borra_un_sitio_y_sus_precios(): void
    {
        $sitio = Site::factory()->create();
        $precio = SiteFare::factory()->create(['site_id' => $sitio->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->deleteJson("/api/v1/admin/sites/{$sitio->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sites', ['id' => $sitio->id]);
        $this->assertDatabaseMissing('site_fares', ['id' => $precio->id]);
    }

    public function test_responde_404_si_el_sitio_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->deleteJson('/api/v1/admin/sites/999999')
            ->assertNotFound();
    }

    public function test_un_pasajero_no_puede_borrar_sitios(): void
    {
        $sitio = Site::factory()->create();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->deleteJson("/api/v1/admin/sites/{$sitio->id}")
            ->assertForbidden();
    }

    public function test_rechaza_el_borrado_sin_token(): void
    {
        $sitio = Site::factory()->create();

        $this->deleteJson("/api/v1/admin/sites/{$sitio->id}")->assertUnauthorized();
    }
}
