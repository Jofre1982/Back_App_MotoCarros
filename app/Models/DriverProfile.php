<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DriverVerificationStatus;
use App\Policies\DriverProfilePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property bool $is_available
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon|null $location_updated_at
 * @property int $cancellation_count
 * @property DriverVerificationStatus $verification_status
 */
#[Fillable(['user_id', 'license_number', 'is_available', 'latitude', 'longitude', 'location_updated_at'])]
#[UsePolicy(DriverProfilePolicy::class)]
class DriverProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_updated_at' => 'datetime',
            'cancellation_count' => 'integer',
            'verification_status' => DriverVerificationStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Los documentos de verificación subidos por este conductor (cédula,
     * licencia, SOAT, tarjeta de propiedad). No fillable a propósito: solo
     * `UploadDriverDocumentAction` y la revisión de un administrador pueden
     * cambiar `verification_status`, nunca la entrada del cliente.
     *
     * @return HasMany<DriverDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }
}
