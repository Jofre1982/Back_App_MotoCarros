<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Datos con los que se da de alta un conductor, ya validados.
 *
 * No lleva rol, por la misma razón que `PassengerRegistration`: el caso de uso
 * es registrar conductores, así que el rol lo fija `RegisterDriverAction` y no
 * hay forma de pedir otro por esta vía.
 *
 * `licenseNumber` es lo único que lo separa del alta de un pasajero. Va acá y
 * no en un DTO aparte del perfil porque cuenta y perfil se crean en la misma
 * operación: una cuenta de conductor sin licencia no es un estado válido.
 */
final readonly class DriverRegistration
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $password,
        public string $licenseNumber,
    ) {}
}
