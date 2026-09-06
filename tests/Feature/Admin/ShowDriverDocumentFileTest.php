<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de GET /api/v1/admin/documents/{document}/file — ver openapi.yaml.
 *
 * Lo que fija esta suite es que solo un administrador pueda leer el archivo
 * (mismo criterio de autorización que aprobar/rechazar), y que la respuesta
 * lleve el contenido real del archivo con su `Content-Type`, no una URL ni
 * un JSON — es lo que permite embeberlo directo en un `<img>` del panel.
 */
class ShowDriverDocumentFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function uri(DriverDocument $document): string
    {
        return "/api/v1/admin/documents/{$document->id}/file";
    }

    private function documentoConArchivo(string $contenido = 'contenido-de-prueba'): DriverDocument
    {
        $perfil = DriverProfile::factory()->create();
        $archivo = UploadedFile::fake()->createWithContent('cedula.jpg', $contenido);
        $path = $archivo->store("driver-documents/{$perfil->id}", 'local');

        return DriverDocument::factory()->create([
            'driver_profile_id' => $perfil->id,
            'path' => $path,
        ]);
    }

    public function test_un_administrador_puede_ver_el_archivo_del_documento(): void
    {
        $documento = $this->documentoConArchivo('contenido-de-la-cedula');

        $respuesta = $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->get($this->uri($documento))
            ->assertOk();

        $respuesta->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('contenido-de-la-cedula', $respuesta->streamedContent());
    }

    public function test_responde_404_si_el_documento_no_existe(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->admin()->create()))
            ->get('/api/v1/admin/documents/999999/file')
            ->assertNotFound();
    }

    public function test_un_conductor_no_puede_ver_el_archivo(): void
    {
        $documento = $this->documentoConArchivo();

        $this->withToken(JWTAuth::fromUser(User::factory()->driver()->create()))
            ->get($this->uri($documento))
            ->assertForbidden();
    }

    public function test_un_pasajero_no_puede_ver_el_archivo(): void
    {
        $documento = $this->documentoConArchivo();

        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->get($this->uri($documento))
            ->assertForbidden();
    }

    public function test_rechaza_la_consulta_sin_token(): void
    {
        $documento = $this->documentoConArchivo();

        $this->getJson($this->uri($documento))->assertUnauthorized();
    }
}
