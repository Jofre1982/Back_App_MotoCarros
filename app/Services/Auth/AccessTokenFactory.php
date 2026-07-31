<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTOs\AuthToken;
use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\Support\Utils;

/**
 * Envuelve un JWT ya emitido en el DTO `AuthToken`, resolviendo cuánta vida le
 * queda.
 *
 * Es un adaptador sobre jwt-auth, no un caso de uso: existe para que las
 * Actions que emiten tokens (registro, y el login de la historia #8) y la que
 * los renueva no repitan el cálculo del vencimiento, que tiene un caso borde
 * propio — ver `secondsUntilExpiry()`.
 */
final class AccessTokenFactory
{
    public function __construct(private readonly JWTAuth $jwt) {}

    public function fromJwt(string $accessToken): AuthToken
    {
        return new AuthToken(
            accessToken: $accessToken,
            expiresInSeconds: $this->secondsUntilExpiry($accessToken),
        );
    }

    /**
     * Segundos que le quedan al token, leídos de su claim `exp`, o `null` si el
     * token no expira.
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
