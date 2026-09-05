<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Policies\DriverDocumentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un documento subido por un conductor para su verificación (documento de
 * identidad, tarjeta de propiedad o foto del vehículo; ver `DocumentType`).
 *
 * `path` apunta al disco `local` —privado, nunca servido por una URL
 * pública como el disco `public`— porque son documentos sensibles.
 *
 * @property DocumentType $type
 * @property string $path
 * @property DocumentStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $reviewed_at
 */
#[Fillable(['driver_profile_id', 'type', 'path', 'status', 'rejection_reason', 'reviewed_at'])]
#[UsePolicy(DriverDocumentPolicy::class)]
class DriverDocument extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }
}
