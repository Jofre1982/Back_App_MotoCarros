<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\AuthToken;
use App\Services\Auth\AccessTokenFactory;
use Tymon\JWTAuth\JWTAuth;

/**
 * Canjea un access token por uno nuevo.
 *
 * Acepta un token ya expirado siempre que siga dentro de la ventana de refresh
 * (`jwt.refresh_ttl`); fuera de ella, jwt-auth lanza una `TokenExpiredException`
 * que el exception handler traduce a 401 (ver bootstrap/app.php).
 */
final class RefreshTokenAction
{
    public function __construct(
        private readonly JWTAuth $jwt,
        private readonly AccessTokenFactory $tokens,
    ) {}

    public function handle(string $token): AuthToken
    {
        return $this->tokens->fromJwt($this->jwt->setToken($token)->refresh());
    }
}
