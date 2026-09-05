<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Documents;

use App\Actions\Documents\ApproveDriverDocumentAction;
use App\Enums\DocumentStatus;
use App\Enums\DriverVerificationStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveDriverDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marca_el_documento_como_aprobado(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'type' => 'identidad',
            'status' => 'pending',
        ]);

        $resultado = (new ApproveDriverDocumentAction)->handle($documento);

        $this->assertSame(DocumentStatus::Approved, $resultado->status);
        $this->assertNotNull($resultado->reviewed_at);
    }

    public function test_no_cambia_la_verificacion_del_conductor_si_falta_otro_documento_obligatorio(): void
    {
        $perfil = DriverProfile::factory()->create();
        $identidad = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'type' => 'identidad',
            'status' => 'pending',
        ]);

        (new ApproveDriverDocumentAction)->handle($identidad);

        $this->assertSame(DriverVerificationStatus::Pending, $perfil->fresh()->verification_status);
    }

    public function test_verifica_al_conductor_cuando_se_aprueban_todos_los_documentos_obligatorios(): void
    {
        $perfil = DriverProfile::factory()->create();
        DriverDocument::factory()->approved()->create(['driver_profile_id' => $perfil->id, 'type' => 'identidad']);
        $tarjeta = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'type' => 'tarjeta_propiedad',
            'status' => 'pending',
        ]);

        (new ApproveDriverDocumentAction)->handle($tarjeta);

        $this->assertSame(DriverVerificationStatus::Verified, $perfil->fresh()->verification_status);
    }
}
