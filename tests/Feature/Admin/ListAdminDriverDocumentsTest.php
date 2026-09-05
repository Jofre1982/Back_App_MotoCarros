<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/admin/documents — ver openapi.yaml.
 */
class ListAdminDriverDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/admin/documents';

    private function conductorConDocumento(string $status = 'pending', string $type = 'identidad'): DriverDocument
    {
        $conductor = User::factory()->driver()->create(['name' => 'Carlos Ramírez']);
        $perfil = DriverProfile::factory()->create(['user_id' => $conductor->id]);

        return DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'type' => $type,
            'status' => $status,
        ]);
    }

    public function test_lista_los_documentos_pendientes_por_defecto(): void
    {
        $documento = $this->conductorConDocumento('pending');
        $this->conductorConDocumento('approved');

        $admin = User::factory()->admin()->create();

        $this->withToken(JWTAuth::fromUser($admin))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $documento->id)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.driver.name', 'Carlos Ramírez');
    }

    public function test_filtra_por_status(): void
    {
        $this->conductorConDocumento('pending');
        $rechazado = $this->conductorConDocumento('rejected');

        $admin = User::factory()->admin()->create();

        $this->withToken(JWTAuth::fromUser($admin))
            ->getJson(self::URI.'?status=rejected')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rechazado->id);
    }

    public function test_un_conductor_no_puede_listar_documentos_de_otros(): void
    {
        $this->conductorConDocumento('pending');

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->getJson(self::URI)
            ->assertForbidden();
    }

    public function test_un_pasajero_no_puede_listar_documentos(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->getJson(self::URI)
            ->assertForbidden();
    }

    public function test_rechaza_la_consulta_sin_token(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }
}
