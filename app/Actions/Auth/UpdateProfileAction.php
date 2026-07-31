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
        // Descarta también la cadena vacía, y no solo `null`: `email` y `phone`
        // son NOT NULL UNIQUE y con el login por email, así que un `''` que
        // llegue hasta acá deja la cuenta sin forma de entrar y la siguiente
        // que repita el vaciado revienta el índice único. `UpdateProfileRequest`
        // ya lo ataja con `sometimes|required`, pero esta Action es invocable
        // desde un job o un comando que no pasa por el Form Request, y su
        // invariante propia es "no escribo un contacto vacío".
        $datos = array_filter(
            [
                'name' => $update->name,
                'email' => $update->email,
                'phone' => $update->phone,
            ],
            static fn (?string $valor): bool => $valor !== null && trim($valor) !== '',
        );

        if ($datos !== []) {
            $user->update($datos);
        }

        return $user;
    }
}
