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
 * Contrato de POST /api/v1/admin/documents/{document}/approve — ver openapi.yaml.
 */
class ApproveDriverDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function uri(DriverDocument $document): string
    {
        return "/api/v1/admin/documents/{$document->id}/approve";
    }

    public function test_aprueba_un_documento_pendiente(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'type' => 'identidad',
            'status' => 'pending',
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson($this->uri($documento))
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('driver_documents', ['id' => $documento->id, 'status' => 'approved']);
    }

    public function test_no_verifica_al_conductor_si_todavia_falta_otro_documento_obligatorio(): void
    {
        $perfil = DriverProfile::factory()->create();
        $identidad = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'type' => 'identidad',
            'status' => 'pending',
        ]);
        // La tarjeta de propiedad ni siquiera se subió todavía.

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson($this->uri($identidad))
            ->assertOk();

        $this->assertSame('pending', $perfil->fresh()->verification_status->value);
    }

    public function test_verifica_al_conductor_cuando_se_aprueba_el_ultimo_documento_obligatorio(): void
    {
        $perfil = DriverProfile::factory()->create();
        DriverDocument::factory()->approved()->create([
            'driver_profile_id' => $perfil->id,
            'type' => 'identidad',
        ]);
        $tarjeta = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'type' => 'tarjeta_propiedad',
            'status' => 'pending',
        ]);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson($this->uri($tarjeta))
            ->assertOk();

        $this->assertSame('verified', $perfil->fresh()->verification_status->value);
    }

    public function test_rechaza_aprobar_un_documento_que_no_esta_pendiente(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->approved()->create(['driver_profile_id' => $perfil->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson($this->uri($documento))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
    }

    public function test_responde_404_si_el_documento_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->postJson('/api/v1/admin/documents/999999/approve')
            ->assertNotFound();
    }

    public function test_un_conductor_no_puede_aprobar_documentos(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create(['driver_profile_id' => $perfil->id]);

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->postJson($this->uri($documento))
            ->assertForbidden();
    }

    public function test_rechaza_la_aprobacion_sin_token(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create(['driver_profile_id' => $perfil->id]);

        $this->postJson($this->uri($documento))->assertUnauthorized();
    }
}
