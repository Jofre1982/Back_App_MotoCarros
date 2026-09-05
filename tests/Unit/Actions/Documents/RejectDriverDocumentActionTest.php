<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Documents;

use App\Actions\Documents\RejectDriverDocumentAction;
use App\Enums\DocumentStatus;
use App\Enums\DriverVerificationStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RejectDriverDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marca_el_documento_como_rechazado_con_el_motivo(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'status' => 'pending',
        ]);

        $resultado = (new RejectDriverDocumentAction)->handle($documento, 'Foto borrosa');

        $this->assertSame(DocumentStatus::Rejected, $resultado->status);
        $this->assertSame('Foto borrosa', $resultado->rejection_reason);
        $this->assertNotNull($resultado->reviewed_at);
    }

    public function test_pone_al_conductor_en_rechazado(): void
    {
        $perfil = DriverProfile::factory()->create();
        $documento = DriverDocument::factory()->create(['driver_profile_id' => $perfil->id, 'status' => 'pending']);

        (new RejectDriverDocumentAction)->handle($documento, 'Foto borrosa');

        $this->assertSame(DriverVerificationStatus::Rejected, $perfil->fresh()->verification_status);
    }
}
