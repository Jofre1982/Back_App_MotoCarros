<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\ProfileUpdate;
use App\Models\User;

/**
 * Actualiza los datos de contacto de una cuenta ya autenticada.
 *
 * Solo escribe los campos que `ProfileUpdate` trae en `null` distinto —el DTO
 * no tiene `role` ni acepta un `id` de destino, así que la única cuenta que
 * esta Action puede tocar es la que el controller le pasa, y lo único que
 * puede cambiar en ella es nombre, email o teléfono.
 */
final class UpdateProfileAction
{
    public function handle(User $user, ProfileUpdate $update): User
    {
        $datos = array_filter(
            [
                'name' => $update->name,
                'email' => $update->email,
                'phone' => $update->phone,
            ],
            static fn (?string $valor): bool => $valor !== null,
        );

        if ($datos !== []) {
            $user->update($datos);
        }

        return $user;
    }
}
