<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El código de recuperación de contraseña vigente de una cuenta (historia
 * técnica de recuperación de contraseña).
 *
 * Uno por cuenta (`user_id` único): pedir un código nuevo reemplaza este,
 * nunca lo duplica — misma estructura que `PhoneVerificationCode`, con la que
 * comparte el canal de envío (SMS) pero no la tabla: son dos flujos distintos
 * (probar que el celular es propio vs. recuperar el acceso a la cuenta) y
 * mezclarlos en la misma fila haría que pedir uno cancelara el otro sin que
 * eso tenga sentido de negocio.
 *
 * @property int $attempts
 * @property Carbon $expires_at
 */
#[Fillable(['user_id', 'code_hash', 'attempts', 'expires_at'])]
class PasswordResetCode extends Model
{
    use HasFactory;

    /**
     * El hash nunca debe salir en una respuesta de la API, mismo criterio que
     * `password` en `User` y `code_hash` en `PhoneVerificationCode`.
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
