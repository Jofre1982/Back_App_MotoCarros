<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\DriverProfilePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool $is_available
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon|null $location_updated_at
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
