<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Datos de contacto a cambiar en la cuenta autenticada, ya validados.
 *
 * Es un PATCH parcial: un campo en `null` significa "no vino en la request",
 * no "bórralo" — ninguno de los tres es nullable en `users`. No lleva `role`
 * ni `id` a propósito, igual que `PassengerRegistration`: el caso de uso es
 * actualizar datos de contacto, así que no hay forma de que uno de los dos
 * llegue hasta la fila por esta vía.
 */
final readonly class ProfileUpdate
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
    ) {}
}
