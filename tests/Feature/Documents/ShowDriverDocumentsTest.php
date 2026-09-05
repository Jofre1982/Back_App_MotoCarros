<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/me/documents — ver openapi.yaml.
 */
class ShowDriverDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/documents';

    private function conductorConPerfil(): User
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        return $conductor->fresh();
    }

    public function test_lista_los_documentos_obligatorios_sin_subir_nada_todavia(): void
    {
        $conductor = $this->conductorConPerfil();

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonCount(3, 'data.documents');

        $tipos = collect($respuesta->json('data.documents'))->pluck('type')->all();
        $this->assertSame(['identidad', 'tarjeta_propiedad', 'foto_vehiculo'], $tipos);

        foreach ($respuesta->json('data.documents') as $documento) {
            $this->assertNull($documento['status']);
        }
    }

    public function test_muestra_el_estado_real_de_los_documentos_ya_subidos(): void
    {
        $conductor = $this->conductorConPerfil();
        $perfil = $conductor->driverProfile;

        DriverDocument::factory()->approved()->create(['driver_profile_id' => $perfil->id, 'type' => 'identidad']);
        DriverDocument::factory()->rejected('Foto borrosa')->create([
            'driver_profile_id' => $perfil->id,
            'type' => 'tarjeta_propiedad',
        ]);

        $respuesta = $this->withToken(JWTAuth::fromUser($conductor))
            ->getJson(self::URI)
            ->assertOk();

        $porTipo = collect($respuesta->json('data.documents'))->keyBy('type');

        $this->assertSame('approved', $porTipo['identidad']['status']);
        $this->assertSame('rejected', $porTipo['tarjeta_propiedad']['status']);
        $this->assertSame('Foto borrosa', $porTipo['tarjeta_propiedad']['rejection_reason']);
    }

    public function test_la_cuenta_de_pasajero_no_puede_consultar_documentos_de_conductor(): void
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
