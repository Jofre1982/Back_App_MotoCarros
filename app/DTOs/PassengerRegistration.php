<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Datos con los que se da de alta un pasajero, ya validados.
 *
 * No lleva rol a propósito: el caso de uso es registrar pasajeros, así que el
 * rol lo fija `RegisterPassengerAction` y no hay forma de pedir otro por esta
 * vía. Que el DTO no tenga el campo es justamente lo que hace imposible que un
 * `role` llegue desde la entrada del cliente hasta la fila de `users`.
 */
final readonly class PassengerRegistration
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $password,
    ) {}
}
