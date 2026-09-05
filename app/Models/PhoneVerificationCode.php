<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El código de verificación de celular vigente de una cuenta (historia #69).
 *
 * Uno por cuenta (`user_id` único): pedir un código nuevo reemplaza este,
 * nunca lo duplica.
 *
 * @property int $attempts
 * @property Carbon $expires_at
 */
#[Fillable(['user_id', 'code_hash', 'attempts', 'expires_at'])]
class PhoneVerificationCode extends Model
{
    use HasFactory;

    /**
     * El hash nunca debe salir en una respuesta de la API, mismo criterio que
     * `password` en `User` y `token` en `DeviceToken`.
     */
    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
