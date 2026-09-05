<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Documents;

use App\Actions\Documents\UploadDriverDocumentAction;
use App\DTOs\DriverDocumentUpload;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadDriverDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_documento_en_estado_pendiente(): void
    {
        Storage::fake('local');

        $perfil = DriverProfile::factory()->create();
        $action = new UploadDriverDocumentAction;

        $documento = $action->handle($perfil, new DriverDocumentUpload(
            type: DocumentType::Identidad,
            file: UploadedFile::fake()->create('cedula.jpg', 100, 'image/jpeg'),
        ));

        $this->assertSame(DocumentStatus::Pending, $documento->status);
        $this->assertSame($perfil->id, $documento->driver_profile_id);
        Storage::disk('local')->assertExists($documento->path);
    }

    public function test_reemplazar_el_documento_borra_el_archivo_anterior(): void
    {
        Storage::fake('local');

        $perfil = DriverProfile::factory()->create();
        $action = new UploadDriverDocumentAction;

        $primero = $action->handle($perfil, new DriverDocumentUpload(
            type: DocumentType::TarjetaPropiedad,
            file: UploadedFile::fake()->create('tarjeta-1.jpg', 100, 'image/jpeg'),
        ));
        $rutaAnterior = $primero->path;

        $segundo = $action->handle($perfil, new DriverDocumentUpload(
            type: DocumentType::TarjetaPropiedad,
            file: UploadedFile::fake()->create('tarjeta-2.jpg', 100, 'image/jpeg'),
        ));

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, DriverDocument::query()->count());
        Storage::disk('local')->assertMissing($rutaAnterior);
    }
}
