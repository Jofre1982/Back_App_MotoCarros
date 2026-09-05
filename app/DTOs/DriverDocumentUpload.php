<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\DocumentType;
use Illuminate\Http\UploadedFile;

/**
 * El documento que un conductor sube para su verificación, ya validado.
 *
 * No lleva el conductor dueño, mismo criterio que `VehicleRegistration`: de
 * quién es el documento lo decide quien invoca la Action —el guard, en el
 * caso del endpoint— y nunca la entrada.
 */
final readonly class DriverDocumentUpload
{
    public function __construct(
        public DocumentType $type,
        public UploadedFile $file,
    ) {}
}
