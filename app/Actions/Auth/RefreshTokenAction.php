<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\AuthToken;
use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\Support\Utils;

/**
 * Canjea un access token por uno nuevo.
 *
 * Acepta un token ya expirado siempre que siga dentro de la ventana de refresh
 * (`jwt.refresh_ttl`); fuera de ella, jwt-auth lanza una `TokenExpiredException`
 * que el exception handler traduce a 401 (ver bootstrap/app.php).
 */
final class RefreshTokenAction
{
    public function __construct(private readonly JWTAuth $jwt) {}

    public function handle(string $token): AuthToken
    {
        $accessToken = $this->jwt->setToken($token)->refresh();

        return new AuthToken(
            accessToken: $accessToken,
            expiresInSeconds: $this->secondsUntilExpiry($accessToken),
        );
    }

    /**
     * Segundos que le quedan al token recién emitido, leídos de su claim `exp`,
     * o `null` si el token no expira.
     *
     * Se lee del token y no de `jwt.ttl` porque el TTL admite `null`: en ese
     * caso jwt-auth omite el claim `exp` y emite un token perpetuo, y
     * `(int) null * 60` habría reportado "expira en 0 segundos" para un token
     * que no vence nunca.
     */
    private function secondsUntilExpiry(string $accessToken): ?int
    {
        $exp = $this->jwt->setToken($accessToken)->getPayload()->get('exp');

        return $exp === null ? null : (int) $exp - Utils::now()->getTimestamp();
    }
}
