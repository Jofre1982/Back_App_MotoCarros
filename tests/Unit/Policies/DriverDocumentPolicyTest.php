<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\DriverDocument;
use App\Models\User;
use App\Policies\DriverDocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverDocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_conductor_puede_subir_y_consultar_sus_documentos(): void
    {
        $policy = new DriverDocumentPolicy;
        $conductor = User::factory()->driver()->create();

        $this->assertTrue($policy->upload($conductor));
        $this->assertTrue($policy->viewAny($conductor));
    }

    public function test_un_pasajero_no_puede_subir_ni_consultar_documentos_de_conductor(): void
    {
        $policy = new DriverDocumentPolicy;
        $pasajero = User::factory()->create();

        $this->assertFalse($policy->upload($pasajero));
        $this->assertFalse($policy->viewAny($pasajero));
    }

    public function test_un_administrador_puede_revisar_documentos(): void
    {
        $policy = new DriverDocumentPolicy;
        $admin = User::factory()->admin()->create();
        $documento = DriverDocument::factory()->create();

        $this->assertTrue($policy->reviewAny($admin));
        $this->assertTrue($policy->review($admin, $documento));
    }

    public function test_un_conductor_no_puede_revisar_documentos_aunque_sean_los_propios(): void
    {
        $policy = new DriverDocumentPolicy;
        $conductor = User::factory()->driver()->create();
        $perfil = $conductor->driverProfile()->create(['license_number' => 'LIC-999999']);
        $documento = DriverDocument::factory()->create(['driver_profile_id' => $perfil->id]);

        $this->assertFalse($policy->reviewAny($conductor));
        $this->assertFalse($policy->review($conductor, $documento));
    }
}
