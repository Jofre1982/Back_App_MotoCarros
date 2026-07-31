<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Credenciales con las que una cuenta ya registrada inicia sesión.
 *
 * No lleva rol: el mismo caso de uso sirve a pasajeros y conductores, y el rol
 * sale de la cuenta encontrada, nunca de la entrada.
 *
 * El `email` llega ya en su forma canónica (minúsculas). Quien construye el DTO
 * es responsable de normalizarlo — desde HTTP lo hace `LoginRequest` con el
 * mismo trait que usan los registros, para que la forma canónica siga viviendo
 * en un solo lugar (ver .claude/STANDARDS.md).
 */
final readonly class LoginCredentials
{
    /**
     * `#[\SensitiveParameter]` por el mismo motivo por el que Laravel lo pone
     * en `EloquentUserProvider::validateCredentials()`: sin él, la contraseña
     * en claro queda como argumento en el stack trace de cualquier excepción
     * que se lance más abajo, y los reporters que vuelcan los argumentos de
     * cada frame la escribirían en el log.
     */
    public function __construct(
        public string $email,
        #[\SensitiveParameter]
        public string $password,
    ) {}
}
