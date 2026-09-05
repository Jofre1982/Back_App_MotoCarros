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
 * Contrato de POST /api/v1/admin/documents/{document}/reject — ver openapi.yaml.
 */
class RejectDriverDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function uri(DriverDocument $document): string
    {
        return "/api/v1/admin/documents/{$document->id}/reject";
    }

    public function test_rechaza_un_documento_pendiente_con_motivo(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'status' => 'pending',
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson($this->uri($documento), ['reason' => 'La foto está borrosa'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('driver_documents', [
            'id' => $documento->id,
            'status' => 'rejected',
            'rejection_reason' => 'La foto está borrosa',
        ]);
        $this->assertSame('rejected', $perfil->fresh()->verification_status->value);
    }

    public function test_rechaza_sin_motivo_responde_422(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create(['driver_profile_id' => $perfil->id, 'status' => 'pending']);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson($this->uri($documento), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseHas('driver_documents', ['id' => $documento->id, 'status' => 'pending']);
    }

    public function test_rechaza_rechazar_un_documento_que_no_esta_pendiente(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->approved()->create(['driver_profile_id' => $perfil->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson($this->uri($documento), ['reason' => 'motivo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
    }

    public function test_un_pasajero_no_puede_rechazar_documentos(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create(['driver_profile_id' => $perfil->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson($this->uri($documento), ['reason' => 'motivo'])
            ->assertForbidden();
    }

    public function test_rechaza_la_solicitud_sin_token(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create(['driver_profile_id' => $perfil->id]);

        $this->postJson($this->uri($documento), ['reason' => 'motivo'])->assertUnauthorized();
    }
}
