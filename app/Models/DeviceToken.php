<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El token de notificaciones push de un dispositivo (historia #67).
 *
 * `token` es único sin importar la cuenta: es el mismo dispositivo físico el
 * que se identifica, y `RegisterDeviceTokenAction` lo muda de dueño en vez de
 * duplicarlo si otra cuenta lo registra después.
 *
 * @property string $token
 * @property DevicePlatform $platform
 */
#[Fillable(['user_id', 'token', 'platform'])]
class DeviceToken extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
