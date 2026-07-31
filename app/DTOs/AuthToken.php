<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Access token JWT emitido por la API, ya resuelto y listo para serializar.
 *
 * Existe para que las Actions de autenticación devuelvan algo tipado en vez de
 * un array suelto, y para que el API Resource no tenga que saber de dónde
 * salieron el token ni su duración.
 */
final readonly class AuthToken
{
    /**
     * @param  int|null  $expiresInSeconds  `null` cuando el token no expira
     *                                      (`jwt.ttl` configurado en `null`).
     */
    public function __construct(
        public string $accessToken,
        public ?int $expiresInSeconds,
        public string $tokenType = 'bearer',
    ) {}
}
