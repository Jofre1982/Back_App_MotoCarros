<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\AuthToken;
use Tymon\JWTAuth\JWTAuth;

/**
 * Canjea un access token por uno nuevo.
 *
 * Acepta un token ya expirado siempre que siga dentro de la ventana de refresh
 * (`jwt.refresh_ttl`); fuera de ella, jwt-auth lanza una `JWTException` que el
 * exception handler traduce a 401 (ver bootstrap/app.php).
 */
final class RefreshTokenAction
{
    public function __construct(private readonly JWTAuth $jwt) {}

    public function handle(string $token): AuthToken
    {
        return new AuthToken(
            accessToken: $this->jwt->setToken($token)->refresh(),
            expiresInSeconds: (int) config('jwt.ttl') * 60,
        );
    }
}
