<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Contrato de POST /api/v1/me/documents — ver openapi.yaml.
 *
 * Un conductor no queda verificado hasta que un administrador revisa sus
 * documentos (historia pendiente); lo que fija esta suite es que el archivo
 * quede del conductor dueño del token, en el disco privado, y que volver a
 * subir el mismo tipo reemplace la fila y el archivo anteriores en vez de
 * acumularlos.
 */
class UploadDriverDocumentTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/me/documents';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function conductorConPerfil(): User
    {
        $conductor = User::factory()->driver()->create();
        DriverProfile::factory()->create(['user_id' => $conductor->id]);

        return $conductor->fresh();
    }

    /**
     * `UploadedFile::fake()->create()` y no `->image()`: este último exige la
     * extensión GD, que ni el entorno local ni el runner de CI (ver
     * .github/workflows/ci.yml) tienen instalada.
     */
    private function archivoFalso(string $nombre = 'cedula.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 100, 'image/jpeg');
    }

    public function test_sube_el_documento_y_lo_asocia_al_perfil_del_conductor(): void
    {
        $conductor = $this->conductorConPerfil();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [
                'type' => 'identidad',
                'file' => $this->archivoFalso(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'identidad')
            ->assertJsonPath('data.status', 'pending');

        $documento = DriverDocument::query()->first();

        $this->assertNotNull($documento);
        $this->assertSame($conductor->driverProfile->id, $documento->driver_profile_id);
        Storage::disk('local')->assertExists($documento->path);
    }

    public function test_el_archivo_queda_en_el_disco_privado_y_no_en_el_publico(): void
    {
        $conductor = $this->conductorConPerfil();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [
                'type' => 'tarjeta_propiedad',
                'file' => $this->archivoFalso('tarjeta.jpg'),
            ])
            ->assertCreated();

        $documento = DriverDocument::query()->firstOrFail();

        // No es una ruta bajo `storage/` públicamente accesible: el path
        // guardado es relativo al disco `local`, que sirve de `storage/app/private`.
        $this->assertStringStartsWith('driver-documents/', $documento->path);
    }

    public function test_volver_a_subir_el_mismo_tipo_reemplaza_el_documento_anterior(): void
    {
        $conductor = $this->conductorConPerfil();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, ['type' => 'identidad', 'file' => $this->archivoFalso('cedula-vieja.jpg')])
            ->assertCreated();

        $rutaAnterior = DriverDocument::query()->firstOrFail()->path;

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, ['type' => 'identidad', 'file' => $this->archivoFalso('cedula-nueva.jpg')])
            ->assertCreated();

        $this->assertDatabaseCount('driver_documents', 1);
        Storage::disk('local')->assertMissing($rutaAnterior);
    }

    public function test_un_documento_rechazado_vuelve_a_pending_al_resubirse(): void
    {
        $conductor = $this->conductorConPerfil();

        DriverDocument::factory()->rejected()->create([
            'driver_profile_id' => $conductor->driverProfile->id,
            'type' => 'tarjeta_propiedad',
        ]);

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [
                'type' => 'tarjeta_propiedad',
                'file' => $this->archivoFalso('tarjeta.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $documento = DriverDocument::query()->firstOrFail();
        $this->assertNull($documento->rejection_reason);
    }

    public function test_rechaza_un_tipo_de_archivo_no_permitido(): void
    {
        $conductor = $this->conductorConPerfil();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [
                'type' => 'identidad',
                'file' => UploadedFile::fake()->create('cedula.exe', 100),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('driver_documents', 0);
    }

    public function test_rechaza_un_tipo_de_documento_desconocido(): void
    {
        $conductor = $this->conductorConPerfil();

        $this->withToken(JWTAuth::fromUser($conductor))
            ->postJson(self::URI, [
                // La licencia de conducción no es obligatoria hoy (decisión de
                // negocio; ver DocumentType), así que sigue sin ser un tipo
                // válido para este endpoint.
                'type' => 'licencia',
                'file' => $this->archivoFalso('doc.jpg'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_la_cuenta_de_pasajero_no_puede_subir_documentos(): void
    {
        $this->withToken(JWTAuth::fromUser(User::factory()->create()))
            ->postJson(self::URI, [
                'type' => 'identidad',
                'file' => $this->archivoFalso(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('driver_documents', 0);
    }

    public function test_rechaza_la_subida_sin_token(): void
    {
        $this->postJson(self::URI, [
            'type' => 'identidad',
            'file' => $this->archivoFalso(),
        ])->assertUnauthorized();
    }
}
